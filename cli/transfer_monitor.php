<?php

/**
 * Transfer Monitoring Cron Job
 * 
 * Automatically checks all pending transfers daily
 * Handles transfer completion, failures, and cleanup
 * 
 * Usage: php transfer_monitor.php [--verbose] [--dry-run]
 * 
 * Cron Setup:
 * 0 2 * * * /usr/bin/php /path/to/transfer_monitor.php >> /var/log/nixiepp-transfers.log 2>&1
 * 
 * @package NixiEpp
 * @version 1.1.0
 */

declare(strict_types=1);

// Bootstrap FOSSBilling
if (!defined('PATH_ROOT')) {
    define('PATH_ROOT', dirname(__DIR__, 4));
}

require_once PATH_ROOT . '/load.php';

// Parse CLI arguments
$verbose = in_array('--verbose', $argv);
$dryRun = in_array('--dry-run', $argv);

echo "========================================\n";
echo "  NixiEpp Transfer Monitor\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

if ($dryRun) {
    echo "[DRY RUN MODE] - No database changes will be made\n\n";
}

try {
    // Get DI container
    $di = include PATH_ROOT . '/di.php';
    
    // Get all services using NixiEpp registrar
    $sql = "SELECT sd.* 
            FROM service_domain sd
            INNER JOIN tld t ON sd.tld = t.id
            WHERE t.registrator = 'NixiEpp'
            AND sd.status IN ('pending_transfer', 'active')";
    
    $stmt = $di['pdo']->prepare($sql);
    $stmt->execute();
    $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($domains) . " domains to check\n\n";
    
    $results = [
        'total' => count($domains),
        'pending_transfers' => 0,
        'completed' => 0,
        'failed' => 0,
        'transferred_out' => 0,
        'errors' => 0,
        'details' => [],
    ];
    
    foreach ($domains as $domain) {
        $domainName = $domain['name'];
        
        try {
            // Get registrar service instance
            $registrarService = new \Box\Mod\Servicedomain\Registrar\NixiEpp\Service($di['config']);
            $registrarService->setDi($di);
            
            // Create TLD model
            $tld = new Model_Tld();
            $tld->id = $domain['tld'];
            $tld->tld = $domain['tld_name'];
            $tld->registrator = 'NixiEpp';
            
            // Check transfer status
            $status = $registrarService->checkTransferStatus($tld, [
                'sld' => explode('.', $domainName)[0],
                'tld' => '.' . $domain['tld_name'],
            ]);
            
            if ($verbose) {
                echo "Checking: {$domainName}\n";
                echo "  Status: " . json_encode($status['transfer_status'] ?? 'unknown') . "\n";
            }
            
            // Update counters
            if (isset($status['transfer_status'])) {
                if (in_array($status['transfer_status'], ['client_approved', 'server_approved'])) {
                    $results['completed']++;
                } elseif (in_array($status['transfer_status'], ['client_rejected', 'client_cancelled'])) {
                    $results['failed']++;
                } elseif ($status['transfer_status'] === 'transferred_out') {
                    $results['transferred_out']++;
                } elseif ($status['transfer_status'] === 'pending') {
                    $results['pending_transfers']++;
                }
            }
            
            $results['details'][] = [
                'domain' => $domainName,
                'status' => $status['transfer_status'] ?? 'unknown',
                'success' => $status['success'] ?? false,
            ];
            
        } catch (Exception $e) {
            $results['errors']++;
            echo "ERROR: {$domainName} - " . $e->getMessage() . "\n";
        }
    }
    
    // Print summary
    echo "\n========================================\n";
    echo "  Summary\n";
    echo "========================================\n\n";
    echo "Total Domains: {$results['total']}\n";
    echo "Pending Transfers: {$results['pending_transfers']}\n";
    echo "Completed: {$results['completed']}\n";
    echo "Failed: {$results['failed']}\n";
    echo "Transferred Out: {$results['transferred_out']}\n";
    echo "Errors: {$results['errors']}\n";
    
    if ($results['errors'] > 0) {
        echo "\n⚠ Some errors occurred. Check logs for details.\n";
    }
    
    echo "\nMonitoring completed at " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "\n✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
