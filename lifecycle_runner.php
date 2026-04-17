<?php

declare(strict_types=1);

/**
 * NIXI Lifecycle Service CLI Runner
 * 
 * Command-line script to execute domain lifecycle processing.
 * Designed for cron job execution.
 * 
 * Usage:
 *   php lifecycle_runner.php [--help] [--dry-run] [--verbose]
 * 
 * Cron Example:
 *   0 2 * * * /usr/bin/php /path/to/lifecycle_runner.php >> /var/log/nixiepp-lifecycle.log 2>&1
 * 
 * @package NixiEpp
 * @version 1.0.0
 */

// Bootstrap FOSSBilling (adjust path as needed)
require_once __DIR__ . '/../../../load.php';

use Box\Mod\Servicedomain\Registrar\NixiEpp\LifecycleService;
use Box\Mod\Servicedomain\Registrar\NixiEpp\EppClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Parse command-line options
$options = getopt('', ['help', 'dry-run', 'verbose']);

if (isset($options['help'])) {
    echo "NIXI Domain Lifecycle Processor\n";
    echo "================================\n\n";
    echo "Usage: php lifecycle_runner.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --help        Show this help message\n";
    echo "  --dry-run     Process without executing EPP commands\n";
    echo "  --verbose     Enable verbose output\n";
    echo "\n";
    exit(0);
}

$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

try {
    // Initialize logger
    $logPath = __DIR__ . '/../../../cache/log/nixiepp-lifecycle.log';
    $logger = new Logger('nixiepp-lifecycle');
    $logger->pushHandler(new StreamHandler($logPath, Logger::INFO));

    if ($verbose) {
        echo "Log file: {$logPath}\n";
    }

    // Get database connection from FOSSBilling
    $di = DI::get();
    $db = $di['db'];
    $pdo = $db->getWrappedConnection(); // Get underlying PDO

    // Get EPP configuration
    $registrarService = $di['mod_service']('servicedomain');
    $registrarConfig = $registrarService->getRegistrarConfig('NixiEpp');

    // Initialize EPP Client
    $eppClient = new EppClient($registrarConfig['config'], $logger);

    // Initialize Lifecycle Service
    $lifecycleService = new LifecycleService($eppClient, $logger, $pdo);

    if ($dryRun) {
        echo "[DRY RUN] Starting lifecycle processing...\n";
        $logger->info('[Lifecycle] DRY RUN MODE - No EPP commands will be executed');
    } else {
        echo "Starting lifecycle processing...\n";
    }

    // Execute lifecycle processing
    $startTime = microtime(true);
    $lifecycleService->processExpiredDomains();
    $endTime = microtime(true);

    $duration = round($endTime - $startTime, 2);
    echo "Processing completed in {$duration} seconds\n";
    $logger->info("[Lifecycle] Processing completed in {$duration} seconds");

} catch (\Exception $e) {
    $errorMessage = "Lifecycle processing failed: " . $e->getMessage();
    echo "ERROR: {$errorMessage}\n";
    
    if (isset($logger)) {
        $logger->crit('[Lifecycle] ' . $errorMessage);
    }
    
    exit(1);
}

exit(0);
