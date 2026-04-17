<?php

declare(strict_types=1);

/**
 * NIXI Domain Lifecycle Management Service
 * 
 * Automates domain state transitions after expiry based on NIXI (.IN registry) lifecycle rules.
 * 
 * Lifecycle:
 * - Day 0: Domain expires
 * - Day 2: Set to clientHold
 * - Day 1-30: Grace period (normal renewal)
 * - Day 31-43: Recovery period (penalty renewal)
 * - Day 44: Delete domain (redemption)
 * 
 * @package NixiEpp
 * @version 1.2.0
 */

namespace Box\Mod\Servicedomain\Registrar\NixiEpp;

use Psr\Log\LoggerInterface;
use PDO;
use PDOException;
use DateTime;
use Exception;

class LifecycleService
{
    /**
     * EPP client instance
     */
    private EppClient $client;

    /**
     * Logger instance
     */
    private LoggerInterface $logger;

    /**
     * Database connection
     */
    private PDO $db;

    /**
     * Batch size for processing
     */
    private const BATCH_SIZE = 100;

    /**
     * Lifecycle thresholds (in days)
     */
    private const DAY_CLIENT_HOLD = 2;
    private const GRACE_PERIOD_END = 30;
    private const RECOVERY_PERIOD_END = 43;
    private const DAY_DELETE = 44;

    /**
     * Days in a year for expiry calculation
     */
    private const DAYS_PER_YEAR = 365;

    /**
     * Constructor
     *
     * @param EppClient $client EPP client for registry operations
     * @param LoggerInterface $logger PSR-3 compliant logger
     * @param PDO $db Database connection
     */
    public function __construct(
        EppClient $client,
        LoggerInterface $logger,
        PDO $db
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Process all expired domains and apply lifecycle rules
     * 
     * This method is designed to be called from a cron job.
     * It processes domains in batches to prevent timeout and memory issues.
     *
     * @return void
     * @throws Exception If database connection fails
     */
    public function processExpiredDomains(): void
    {
        $this->log('Starting expired domain lifecycle processing');

        $offset = 0;
        $totalProcessed = 0;
        $totalErrors = 0;

        do {
            // Fetch batch of expired domains
            $domains = $this->fetchExpiredDomains(self::BATCH_SIZE, $offset);
            $batchCount = count($domains);

            if ($batchCount === 0) {
                break;
            }

            $this->log("Processing batch of {$batchCount} domains (offset: {$offset})");

            // Process each domain in the batch
            foreach ($domains as $domain) {
                try {
                    $this->processSingleDomain($domain);
                    $totalProcessed++;
                } catch (Exception $e) {
                    $totalErrors++;
                    $this->log("Error processing domain {$domain['domain']}: {$e->getMessage()}");
                }
            }

            // Move to next batch
            $offset += self::BATCH_SIZE;

        } while ($batchCount === self::BATCH_SIZE);

        $this->log(
            "Lifecycle processing complete. "
            . "Processed: {$totalProcessed}, Errors: {$totalErrors}"
        );
    }

    /**
     * Handle domain renewal with NIXI-specific rules
     * 
     * During grace/recovery period (days 1-43):
     * - Registry auto-renews for 1 year internally
     * - DO NOT send EPP renew for the first year
     * - Only send EPP renew for additional years beyond 1
     *
     * @param string $domain Domain name (e.g., "example.in")
     * @param int $years Number of years to renew
     * @param DateTime $currentExpiry Current expiry date
     * @return void
     * @throws Exception If renewal fails
     */
    public function handleRenewal(string $domain, int $years, DateTime $currentExpiry): void
    {
        // Calculate days since expiry (can be negative if renewed before expiry)
        $daysSinceExpiry = $this->calculateDaysSinceExpiry($currentExpiry);

        // If within grace/recovery period (days 1-43 after expiry)
        if ($daysSinceExpiry >= 1 && $daysSinceExpiry <= self::RECOVERY_PERIOD_END) {
            $this->handleGraceRecoveryRenewal($domain, $years, $currentExpiry, $daysSinceExpiry);
        } else {
            // Normal renewal (before expiry or after recovery period)
            $this->handleNormalRenewal($domain, $years, $currentExpiry);
        }
    }

    /**
     * Process a single domain through lifecycle rules
     *
     * @param array<string, mixed> $domain Domain data from database
     * @return void
     * @throws Exception If processing fails
     */
    private function processSingleDomain(array $domain): void
    {
        $domainName = $domain['domain'];
        $expiryDate = new DateTime($domain['expiry_date']);
        $daysSinceExpiry = $this->calculateDaysSinceExpiry($expiryDate);
        $currentStatus = $domain['status'] ?? 'active';

        $this->log("Processing {$domainName} - Day {$daysSinceExpiry} (Status: {$currentStatus})");

        // Apply lifecycle rules based on days since expiry
        switch (true) {
            case $daysSinceExpiry === self::DAY_CLIENT_HOLD:
                $this->applyClientHold($domainName, $currentStatus);
                break;

            case $daysSinceExpiry >= 1 && $daysSinceExpiry <= self::GRACE_PERIOD_END:
                $this->handleGracePeriod($domainName, $daysSinceExpiry);
                break;

            case $daysSinceExpiry >= 31 && $daysSinceExpiry <= self::RECOVERY_PERIOD_END:
                $this->handleRecoveryPeriod($domainName, $daysSinceExpiry);
                break;

            case $daysSinceExpiry === self::DAY_DELETE:
                $this->applyDomainDeletion($domainName, $currentStatus);
                break;

            default:
                // No action needed for this day
                break;
        }
    }

    /**
     * Set domain to clientHold status on Day 2
     *
     * @param string $domain Domain name
     * @param string $currentStatus Current domain status
     * @return void
     * @throws Exception If EPP call fails
     */
    private function applyClientHold(string $domain, string $currentStatus): void
    {
        // Skip if already on hold
        if (stripos($currentStatus, 'clienthold') !== false) {
            $this->log("Domain {$domain} already has clientHold status, skipping");
            return;
        }

        try {
            $this->client->updateDomainStatus($domain, ['clientHold'], []);
            $this->updateDomainStatus($domain, 'clientHold');
            $this->log("Successfully set clientHold for {$domain}");
        } catch (Exception $e) {
            $this->log("Failed to set clientHold for {$domain}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Handle grace period (Days 1-30)
     * 
     * No automatic EPP action needed. Domain can be renewed normally.
     *
     * @param string $domain Domain name
     * @param int $days Days since expiry
     * @return void
     */
    private function handleGracePeriod(string $domain, int $days): void
    {
        // Grace period - no EPP action required
        // Customer can renew normally during this period
        $this->log("Domain {$domain} is in grace period (Day {$days})");
    }

    /**
     * Handle recovery period (Days 31-43)
     * 
     * No automatic EPP action. Renewal allowed with penalty fee.
     *
     * @param string $domain Domain name
     * @param int $days Days since expiry
     * @return void
     */
    private function handleRecoveryPeriod(string $domain, int $days): void
    {
        // Recovery period - no EPP action required
        // Customer can renew with penalty during this period
        $this->log("Domain {$domain} is in recovery period (Day {$days})");
    }

    /**
     * Delete domain on Day 44 (send to redemption)
     *
     * @param string $domain Domain name
     * @param string $currentStatus Current domain status
     * @return void
     * @throws Exception If EPP call fails
     */
    private function applyDomainDeletion(string $domain, string $currentStatus): void
    {
        // Skip if already deleted or in redemption
        if (stripos($currentStatus, 'redemption') !== false 
            || stripos($currentStatus, 'deleted') !== false) {
            $this->log("Domain {$domain} already deleted/in redemption, skipping");
            return;
        }

        try {
            $this->client->deleteDomain($domain);
            $this->updateDomainStatus($domain, 'redemption');
            $this->markAutoRenewed($domain);
            $this->log("Successfully sent delete for {$domain} (moved to redemption)");
        } catch (Exception $e) {
            $this->log("Failed to delete {$domain}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Handle renewal during grace/recovery period
     * 
     * NIXI auto-renews for 1 year internally, so we only renew extra years via EPP.
     *
     * @param string $domain Domain name
     * @param int $years Requested renewal years
     * @param DateTime $currentExpiry Current expiry date
     * @param int $daysSinceExpiry Days since expiry
     * @return void
     * @throws Exception If renewal fails
     */
    private function handleGraceRecoveryRenewal(
        string $domain,
        int $years,
        DateTime $currentExpiry,
        int $daysSinceExpiry
    ): void {
        $period = $daysSinceExpiry <= self::GRACE_PERIOD_END ? 'grace' : 'recovery';
        $this->log("Processing {$period} period renewal for {$domain} ({$years} years, Day {$daysSinceExpiry})");

        // NIXI auto-renews for 1 year during grace/recovery
        // We only need to handle additional years beyond the first
        if ($years === 1) {
            // Registry already renewed for 1 year internally
            // Just update local database
            $newExpiry = clone $currentExpiry;
            $newExpiry->modify('+1 year');
            $this->updateDomainExpiry($domain, $newExpiry);
            $this->clearAutoRenewed($domain);
            $this->log("Updated local expiry for {$domain} (no EPP call needed - auto-renewed by registry)");
        } else {
            // Customer wants more than 1 year
            // Registry handles first year, we renew the rest via EPP
            $extraYears = $years - 1;
            $this->client->renewDomain($domain, $extraYears);
            
            $newExpiry = clone $currentExpiry;
            $newExpiry->modify("+{$years} years");
            $this->updateDomainExpiry($domain, $newExpiry);
            $this->clearAutoRenewed($domain);
            $this->log("Renewed {$domain} for {$extraYears} extra years via EPP (first year auto-renewed)");
        }

        // Reactivate domain if it was on hold
        $this->reactivateDomain($domain);
    }

    /**
     * Handle normal renewal (before expiry or after recovery period)
     *
     * @param string $domain Domain name
     * @param int $years Requested renewal years
     * @param DateTime $currentExpiry Current expiry date
     * @return void
     * @throws Exception If renewal fails
     */
    private function handleNormalRenewal(
        string $domain,
        int $years,
        DateTime $currentExpiry
    ): void {
        $this->log("Processing normal renewal for {$domain} ({$years} years)");

        // Standard EPP renewal
        $this->client->renewDomain($domain, $years);

        $newExpiry = clone $currentExpiry;
        $newExpiry->modify("+{$years} years");
        $this->updateDomainExpiry($domain, $newExpiry);
        $this->log("Successfully renewed {$domain} for {$years} years via EPP");

        // Reactivate domain if it was on hold
        $this->reactivateDomain($domain);
    }

    /**
     * Reactivate domain by removing clientHold status
     *
     * @param string $domain Domain name
     * @return void
     */
    private function reactivateDomain(string $domain): void
    {
        try {
            $this->client->updateDomainStatus($domain, [], ['clientHold']);
            $this->updateDomainStatus($domain, 'active');
            $this->log("Reactivated domain {$domain} (removed clientHold)");
        } catch (Exception $e) {
            // Non-critical - log but don't fail
            $this->log("Warning: Failed to reactivate {$domain}: {$e->getMessage()}");
        }
    }

    /**
     * Fetch expired domains from database in batches
     *
     * @param int $limit Number of domains to fetch
     * @param int $offset Offset for pagination
     * @return array<array<string, mixed>> Array of domain data
     * @throws PDOException If database query fails
     */
    private function fetchExpiredDomains(int $limit, int $offset): array
    {
        $sql = "
            SELECT 
                id,
                domain,
                expiry_date,
                status,
                auto_renewed,
                years
            FROM domains
            WHERE expiry_date < CURDATE()
            ORDER BY expiry_date ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate days since domain expiry
     *
     * @param DateTime $expiryDate Domain expiry date
     * @return int Days since expiry (negative if not yet expired)
     */
    private function calculateDaysSinceExpiry(DateTime $expiryDate): int
    {
        $today = new DateTime('today');
        $interval = $today->diff($expiryDate);
        
        // Negative means expired (expiry date is in the past)
        return -$interval->days;
    }

    /**
     * Update domain status in database
     *
     * @param string $domain Domain name
     * @param string $status New status
     * @return void
     * @throws PDOException If update fails
     */
    private function updateDomainStatus(string $domain, string $status): void
    {
        $sql = "UPDATE domains SET status = :status WHERE domain = :domain";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':domain' => $domain,
        ]);
    }

    /**
     * Update domain expiry date in database
     *
     * @param string $domain Domain name
     * @param DateTime $newExpiry New expiry date
     * @return void
     * @throws PDOException If update fails
     */
    private function updateDomainExpiry(string $domain, DateTime $newExpiry): void
    {
        $sql = "UPDATE domains SET expiry_date = :expiry_date WHERE domain = :domain";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':expiry_date' => $newExpiry->format('Y-m-d'),
            ':domain' => $domain,
        ]);
    }

    /**
     * Mark domain as auto-renewed by registry
     *
     * @param string $domain Domain name
     * @return void
     * @throws PDOException If update fails
     */
    private function markAutoRenewed(string $domain): void
    {
        $sql = "UPDATE domains SET auto_renewed = 1 WHERE domain = :domain";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':domain' => $domain]);
    }

    /**
     * Clear auto-renewed flag after manual renewal
     *
     * @param string $domain Domain name
     * @return void
     * @throws PDOException If update fails
     */
    private function clearAutoRenewed(string $domain): void
    {
        $sql = "UPDATE domains SET auto_renewed = 0 WHERE domain = :domain";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':domain' => $domain]);
    }

    /**
     * Log message with lifecycle prefix
     *
     * @param string $message Log message
     * @return void
     */
    private function log(string $message): void
    {
        $this->logger->info('[Lifecycle] ' . $message);
    }
}
