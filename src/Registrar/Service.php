<?php

/**
 * NixiEpp Registrar Module for FOSSBilling
 * 
 * Production-ready EPP (Extensible Provisioning Protocol) registrar module
 * supporting TLS-encrypted connections for domain registration management.
 * 
 * @package NixiEpp
 * @version 1.2.0
 * @author NixiEpp
 * @license MIT
 */

namespace Box\Mod\Servicedomain\Registrar\NixiEpp;

use Box\Mod\Servicedomain\Registrar\AdapterAbstract;

class Service extends AdapterAbstract
{
    /**
     * EPP Client instance
     */
    private ?EppClient $eppClient = null;

    /**
     * Module configuration
     */
    protected array $config = [];

    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->config = $config;
    }

    /**
     * Get module information
     */
    public static function getInfo(): array
    {
        return [
            'name' => 'NixiEpp Registrar',
            'version' => '1.2.0',
            'description' => 'Production-ready EPP registrar module with TLS support for FOSSBilling',
            'author' => 'NixiEpp',
            'url' => 'https://github.com/nixiepp',
            'help_url' => 'https://github.com/nixiepp/docs',
        ];
    }

    /**
     * Test connection to EPP server
     */
    public function testConnection(): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();
            $client->logout();
            $client->disconnect();
            
            return true;
        } catch (\Exception $e) {
            $this->getLog()->crit('Connection test failed: ' . $e->getMessage());
            throw new \Exception('Connection test failed: ' . $e->getMessage());
        }
    }

    /**
     * Register a new domain
     */
    public function registerDomain(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            
            // Create contact if needed
            $contactId = $this->createContact($client, $domainData);
            
            // Create domain
            $result = $client->createDomain(
                $domainName,
                $contactId,
                $domainData['ns'] ?? [],
                $domainData['registration_period'] ?? 1,
                $domainData
            );

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Domain registered successfully: ' . $domainName);
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Domain registration failed: ' . $e->getMessage());
            throw new \Exception('Domain registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Transfer a domain
     */
    public function transferDomain(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $authCode = $domainData['auth_code'] ?? '';

            $result = $client->transferDomain($domainName, $authCode, $domainData);

            if (!$result->isSuccess()) {
                throw new \Exception('Transfer request failed: ' . $result->getMessage());
            }

            $client->logout();
            $client->disconnect();

            // Log transfer initiation
            $this->getLog()->info('Domain transfer initiated: ' . $domainName);
            
            // Mark transfer as pending in database
            $this->markTransferPending($domainName, $result->getResultCode());
            
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Domain transfer failed: ' . $e->getMessage());
            throw new \Exception('Domain transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Check transfer status (manual check via button)
     */
    public function checkTransferStatus(\Model_Tld $tld, array $domainData): array
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            
            // Get domain info to check transfer status
            $domainInfo = $client->infoDomain($domainName);
            
            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Transfer status checked for: ' . $domainName);
            
            return [
                'success' => true,
                'domain' => $domainName,
                'status' => $domainInfo['status'] ?? [],
                'transfer_status' => $domainInfo['transfer_status'] ?? 'none',
                'expiry_date' => $domainInfo['expiry_date'] ?? null,
                'registrar' => $domainInfo['registrar'] ?? null,
                'message' => 'Transfer status retrieved successfully',
            ];
        } catch (\Exception $e) {
            $this->getLog()->err('Failed to check transfer status: ' . $e->getMessage());
            return [
                'success' => false,
                'domain' => $domainData['sld'] . '.' . $tld->tld,
                'message' => 'Failed to check transfer status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Automatically check all pending transfers (daily cron job)
     */
    public function checkPendingTransfers(): array
    {
        $results = [
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
            'pending' => 0,
            'transferred_out' => 0,
            'details' => [],
        ];

        try {
            // Get all pending transfers from database
            $pendingTransfers = $this->getPendingTransfers();
            $results['total'] = count($pendingTransfers);

            foreach ($pendingTransfers as $transfer) {
                $domainName = $transfer['domain_name'];
                $daysSinceInitiated = $transfer['days_since_initiated'];

                try {
                    // Check transfer status via EPP
                    $status = $this->getTransferStatus($domainName);

                    // Handle based on status
                    if ($status['transfer_status'] === 'client_approved' || 
                        $status['transfer_status'] === 'server_approved') {
                        // Transfer completed - incoming
                        $this->completeTransferIn($domainName, $status);
                        $results['completed']++;
                        $results['details'][] = [
                            'domain' => $domainName,
                            'action' => 'transfer_in_completed',
                            'status' => $status['transfer_status'],
                        ];

                    } elseif ($status['transfer_status'] === 'client_rejected' || 
                              $status['transfer_status'] === 'client_cancelled') {
                        // Transfer rejected/cancelled
                        $this->failTransfer($domainName, $status);
                        $results['failed']++;
                        $results['details'][] = [
                            'domain' => $domainName,
                            'action' => 'transfer_failed',
                            'status' => $status['transfer_status'],
                        ];

                    } elseif ($status['is_transferred_out'] === true) {
                        // Domain transferred out to another registrar
                        $this->handleTransferOut($domainName, $status, $daysSinceInitiated);
                        $results['transferred_out']++;
                        $results['details'][] = [
                            'domain' => $domainName,
                            'action' => 'transfer_out_detected',
                            'status' => 'transferred_out',
                        ];

                    } else {
                        // Still pending
                        $results['pending']++;
                        $results['details'][] = [
                            'domain' => $domainName,
                            'action' => 'still_pending',
                            'days' => $daysSinceInitiated,
                            'status' => $status['transfer_status'] ?? 'pending',
                        ];
                    }

                } catch (\Exception $e) {
                    $this->getLog()->err("Failed to check transfer for {$domainName}: " . $e->getMessage());
                    $results['failed']++;
                    $results['details'][] = [
                        'domain' => $domainName,
                        'action' => 'check_failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->getLog()->info("Pending transfer check completed: " . json_encode($results));

        } catch (\Exception $e) {
            $this->getLog()->err('Failed to check pending transfers: ' . $e->getMessage());
            throw $e;
        }

        return $results;
    }

    /**
     * Get detailed transfer status for a domain
     */
    private function getTransferStatus(string $domainName): array
    {
        $client = $this->getEppClient();
        $client->connect();
        $client->login();

        $domainInfo = $client->infoDomain($domainName);

        $client->logout();
        $client->disconnect();

        // Determine transfer status from domain info
        $statuses = $domainInfo['status'] ?? [];
        $transferStatus = 'none';
        $isTransferredOut = false;

        // Check for pending transfer status
        foreach ($statuses as $status) {
            if (stripos($status, 'pendingTransfer') !== false) {
                $transferStatus = 'pending';
            }
            if (stripos($status, 'clientTransferProhibited') !== false) {
                $transferStatus = 'locked';
            }
        }

        // Check if domain is still with our registrar
        $currentRegistrar = $domainInfo['registrar'] ?? '';
        $ourRegistrarId = $this->config['username'] ?? '';
        
        if (!empty($currentRegistrar) && $currentRegistrar !== $ourRegistrarId) {
            $isTransferredOut = true;
            $transferStatus = 'transferred_out';
        }

        return [
            'transfer_status' => $transferStatus,
            'is_transferred_out' => $isTransferredOut,
            'domain_info' => $domainInfo,
            'current_registrar' => $currentRegistrar,
        ];
    }

    /**
     * Mark transfer as pending in database
     */
    private function markTransferPending(string $domainName, int $resultCode): void
    {
        $di = $this->getDi();
        $sql = "INSERT INTO service_domain_transfer 
                (domain_name, status, transfer_initiated_at, last_checked_at, result_code)
                VALUES (:domain, 'pending', NOW(), NOW(), :code)
                ON DUPLICATE KEY UPDATE 
                status = 'pending',
                transfer_initiated_at = NOW(),
                last_checked_at = NOW(),
                result_code = :code";

        $stmt = $di['pdo']->prepare($sql);
        $stmt->execute([
            ':domain' => $domainName,
            ':code' => $resultCode,
        ]);

        $this->getLog()->info("Transfer marked as pending: {$domainName}");
    }

    /**
     * Get all pending transfers from database
     */
    private function getPendingTransfers(): array
    {
        $di = $this->getDi();
        $sql = "SELECT 
                    id,
                    domain_name,
                    status,
                    transfer_initiated_at,
                    last_checked_at,
                    DATEDIFF(NOW(), transfer_initiated_at) as days_since_initiated
                FROM service_domain_transfer
                WHERE status IN ('pending', 'checking')
                ORDER BY transfer_initiated_at ASC";

        $stmt = $di['pdo']->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Complete incoming transfer
     */
    private function completeTransferIn(string $domainName, array $status): void
    {
        $di = $this->getDi();
        
        // Update transfer record
        $sql = "UPDATE service_domain_transfer 
                SET status = 'completed',
                    completed_at = NOW(),
                    last_checked_at = NOW(),
                    transfer_status = :transfer_status
                WHERE domain_name = :domain";

        $stmt = $di['pdo']->prepare($sql);
        $stmt->execute([
            ':domain' => $domainName,
            ':transfer_status' => $status['transfer_status'],
        ]);

        // Update domain service status to active
        $sql = "UPDATE service_domain 
                SET status = 'active'
                WHERE name = :domain";

        $stmt = $di['pdo']->prepare($sql);
        $stmt->execute([':domain' => $domainName]);

        $this->getLog()->info("Transfer IN completed for: {$domainName}");
    }

    /**
     * Mark transfer as failed
     */
    private function failTransfer(string $domainName, array $status): void
    {
        $di = $this->getDi();
        
        $sql = "UPDATE service_domain_transfer 
                SET status = 'failed',
                    failed_at = NOW(),
                    last_checked_at = NOW(),
                    failure_reason = :reason
                WHERE domain_name = :domain";

        $stmt = $di['pdo']->prepare($sql);
        $stmt->execute([
            ':domain' => $domainName,
            ':reason' => 'Transfer ' . $status['transfer_status'],
        ]);

        $this->getLog()->info("Transfer failed for: {$domainName} - " . $status['transfer_status']);
    }

    /**
     * Handle domain transferred out (delete after 7 days)
     */
    private function handleTransferOut(string $domainName, array $status, int $daysSinceInitiated): void
    {
        $di = $this->getDi();

        // Check if 7 days have passed since transfer out was initiated
        if ($daysSinceInitiated >= 7) {
            // Delete domain from database
            $sql = "DELETE FROM service_domain WHERE name = :domain";
            $stmt = $di['pdo']->prepare($sql);
            $stmt->execute([':domain' => $domainName]);

            // Delete transfer record
            $sql = "UPDATE service_domain_transfer 
                    SET status = 'transferred_out',
                        transferred_out_at = NOW(),
                        last_checked_at = NOW()
                    WHERE domain_name = :domain";

            $stmt = $di['pdo']->prepare($sql);
            $stmt->execute([':domain' => $domainName]);

            $this->getLog()->warning("Domain transferred out and removed from DB: {$domainName} (after {$daysSinceInitiated} days)");
        } else {
            // Still within 7-day window, keep monitoring
            $sql = "UPDATE service_domain_transfer 
                    SET status = 'transferring_out',
                        last_checked_at = NOW(),
                        transfer_status = 'transferred_out'
                    WHERE domain_name = :domain";

            $stmt = $di['pdo']->prepare($sql);
            $stmt->execute([':domain' => $domainName]);

            $this->getLog()->info("Domain transferring out: {$domainName} (Day {$daysSinceInitiated}/7)");
        }
    }

    /**
     * Renew a domain
     */
    public function renewDomain(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $period = $domainData['renew_years'] ?? 1;

            $result = $client->renewDomain($domainName, $period);

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Domain renewed: ' . $domainName . ' for ' . $period . ' year(s)');
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Domain renewal failed: ' . $e->getMessage());
            throw new \Exception('Domain renewal failed: ' . $e->getMessage());
        }
    }

    /**
     * Get domain information
     */
    public function getDomainDetails(\Model_Tld $tld, array $domainData): array
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $info = $client->infoDomain($domainName);

            $client->logout();
            $client->disconnect();

            return $info;
        } catch (\Exception $e) {
            $this->getLog()->err('Failed to get domain details: ' . $e->getMessage());
            throw new \Exception('Failed to get domain details: ' . $e->getMessage());
        }
    }

    /**
     * Update domain nameservers
     */
    public function modifyNs(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $nameservers = $domainData['ns'] ?? [];

            $result = $client->updateDomainNs($domainName, $nameservers);

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Nameservers updated for: ' . $domainName);
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Nameserver update failed: ' . $e->getMessage());
            throw new \Exception('Nameserver update failed: ' . $e->getMessage());
        }
    }

    /**
     * Get domain transfer code (Auth Code)
     */
    public function getTransferCode(\Model_Tld $tld, array $domainData): string
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $authCode = $client->getTransferCode($domainName);

            $client->logout();
            $client->disconnect();

            return $authCode;
        } catch (\Exception $e) {
            $this->getLog()->err('Failed to get transfer code: ' . $e->getMessage());
            throw new \Exception('Failed to get transfer code: ' . $e->getMessage());
        }
    }

    /**
     * Lock domain
     */
    public function lockDomain(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $result = $client->lockDomain($domainName);

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Domain locked: ' . $domainName);
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Domain lock failed: ' . $e->getMessage());
            throw new \Exception('Domain lock failed: ' . $e->getMessage());
        }
    }

    /**
     * Unlock domain
     */
    public function unlockDomain(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $result = $client->unlockDomain($domainName);

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Domain unlocked: ' . $domainName);
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Domain unlock failed: ' . $e->getMessage());
            throw new \Exception('Domain unlock failed: ' . $e->getMessage());
        }
    }

    /**
     * Enable privacy protection (WHOIS)
     */
    public function enablePrivacyProtection(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $result = $client->enablePrivacyProtection($domainName);

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Privacy protection enabled: ' . $domainName);
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Failed to enable privacy protection: ' . $e->getMessage());
            throw new \Exception('Failed to enable privacy protection: ' . $e->getMessage());
        }
    }

    /**
     * Disable privacy protection (WHOIS)
     */
    public function disablePrivacyProtection(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $result = $client->disablePrivacyProtection($domainName);

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Privacy protection disabled: ' . $domainName);
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Failed to disable privacy protection: ' . $e->getMessage());
            throw new \Exception('Failed to disable privacy protection: ' . $e->getMessage());
        }
    }

    /**
     * Delete domain
     */
    public function deleteDomain(\Model_Tld $tld, array $domainData): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $domainName = $domainData['sld'] . '.' . $tld->tld;
            $result = $client->deleteDomain($domainName);

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Domain deleted: ' . $domainName);
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Domain deletion failed: ' . $e->getMessage());
            throw new \Exception('Domain deletion failed: ' . $e->getMessage());
        }
    }

    /**
     * Check domain availability
     */
    public function isDomainAvailable(string $domainName): bool
    {
        try {
            $client = $this->getEppClient();
            $client->connect();
            $client->login();

            $result = $client->checkDomain($domainName);

            $client->logout();
            $client->disconnect();

            return $result['available'] ?? false;
        } catch (\Exception $e) {
            $this->getLog()->err('Domain availability check failed: ' . $e->getMessage());
            throw new \Exception('Domain availability check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get EPP client instance
     */
    private function getEppClient(): EppClient
    {
        if ($this->eppClient === null) {
            $this->eppClient = new EppClient([
                'host' => $this->config['config']['host'] ?? '',
                'port' => $this->config['config']['port'] ?? 700,
                'username' => $this->config['config']['username'] ?? '',
                'password' => $this->config['config']['password'] ?? '',
                'new_password' => $this->config['config']['new_password'] ?? '',
                'prefix' => $this->config['config']['prefix'] ?? 'NIXI',
                'ssl_cert_path' => $this->config['config']['ssl_cert_path'] ?? '',
                'ssl_key_path' => $this->config['config']['ssl_key_path'] ?? '',
                'ssl_ca_path' => $this->config['config']['ssl_ca_path'] ?? '',
                'timeout' => $this->config['config']['timeout'] ?? 30,
            ], $this->getLog());
        }

        return $this->eppClient;
    }

    /**
     * Create or retrieve contact for domain registration
     */
    private function createContact(EppClient $client, array $domainData): string
    {
        // Check if contact already exists
        $contactId = $this->generateContactId($domainData);
        
        try {
            $client->infoContact($contactId);
            return $contactId;
        } catch (\Exception $e) {
            // Contact doesn't exist, create it
            return $client->createContact($contactId, $domainData);
        }
    }

    /**
     * Generate contact ID
     */
    private function generateContactId(array $domainData): string
    {
        $prefix = $this->config['config']['prefix'] ?? 'NIXI';
        $email = $domainData['contact_email'] ?? '';
        $hash = substr(md5($email), 0, 8);
        return strtoupper($prefix . '-C' . $hash);
    }

    /**
     * Required configuration fields
     */
    public static function getConfig(): array
    {
        return [
            'supports_registration' => [
                'type' => 'radio',
                'title' => 'Supports Registration',
                'options' => ['1' => 'Yes', '0' => 'No'],
                'default' => '1',
            ],
            'supports_transfer' => [
                'type' => 'radio',
                'title' => 'Supports Transfer',
                'options' => ['1' => 'Yes', '0' => 'No'],
                'default' => '1',
            ],
            'host' => [
                'type' => 'text',
                'title' => 'EPP Server Host',
                'required' => true,
                'default' => 'epp.registry.com',
            ],
            'port' => [
                'type' => 'text',
                'title' => 'EPP Server Port',
                'required' => true,
                'default' => '700',
            ],
            'username' => [
                'type' => 'text',
                'title' => 'EPP Username',
                'required' => true,
            ],
            'password' => [
                'type' => 'password',
                'title' => 'EPP Password',
                'required' => true,
            ],
            'new_password' => [
                'type' => 'password',
                'title' => 'EPP New Password (for password change)',
                'required' => false,
            ],
            'prefix' => [
                'type' => 'text',
                'title' => 'Object Prefix',
                'required' => true,
                'default' => 'NIXI',
                'description' => 'Prefix for contact and domain handles',
            ],
            'ssl_cert_path' => [
                'type' => 'text',
                'title' => 'SSL Certificate Path',
                'required' => false,
                'description' => 'Path to client SSL certificate',
            ],
            'ssl_key_path' => [
                'type' => 'text',
                'title' => 'SSL Key Path',
                'required' => false,
                'description' => 'Path to client SSL private key',
            ],
            'ssl_ca_path' => [
                'type' => 'text',
                'title' => 'SSL CA Certificate Path',
                'required' => false,
                'description' => 'Path to CA certificate bundle',
            ],
            'timeout' => [
                'type' => 'text',
                'title' => 'Connection Timeout (seconds)',
                'required' => false,
                'default' => '30',
            ],
        ];
    }
}
