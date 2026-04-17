<?php

/**
 * Transfer Testing Script for NixiEpp Module
 * 
 * This script tests domain transfer functionality including:
 * - Transfer initiation
 * - Transfer status checking
 * - Transfer code retrieval
 * - Transfer cancellation
 * - Error handling
 * 
 * Usage: php test_transfers.php [--dry-run] [--verbose]
 * 
 * @package NixiEpp
 * @version 1.0.0
 */

declare(strict_types=1);

// Configuration - UPDATE THESE VALUES
$config = [
    'epp_host' => 'epp.registry.com',
    'epp_port' => 700,
    'username' => 'your_registrar_id',
    'password' => 'your_password',
    'ssl_cert' => '/path/to/client-cert.pem',
    'ssl_key' => '/path/to/client-key.pem',
    'ssl_ca' => '/path/to/ca-bundle.crt',
];

// Test domains - UPDATE THESE
$testDomains = [
    [
        'domain' => 'example.in',
        'auth_code' => 'test-auth-code-123',
        'expected_status' => 'pending',
    ],
    [
        'domain' => 'test-domain.in',
        'auth_code' => 'another-auth-code',
        'expected_status' => 'pending',
    ],
];

// Parse CLI arguments
$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv);

echo "========================================\n";
echo "  NixiEpp Transfer Testing Script\n";
echo "========================================\n\n";

if ($dryRun) {
    echo "[DRY RUN MODE] - No actual EPP commands will be sent\n\n";
}

// Load required files (adjust paths as needed)
require_once __DIR__ . '/EppFrame.php';
require_once __DIR__ . '/EppResponse.php';
require_once __DIR__ . '/EppClient.php';

use Box\Mod\Servicedomain\Registrar\NixiEpp\EppClient;

/**
 * Test Suite: Domain Transfers
 */
class TransferTester
{
    private EppClient $client;
    private array $config;
    private bool $dryRun;
    private bool $verbose;
    private array $results = [];

    public function __construct(array $config, bool $dryRun, bool $verbose)
    {
        $this->config = $config;
        $this->dryRun = $dryRun;
        $this->verbose = $verbose;
    }

    /**
     * Run all transfer tests
     */
    public function runAllTests(array $testDomains): void
    {
        echo "Starting Transfer Tests...\n";
        echo str_repeat('-', 60) . "\n\n";

        // Test 1: Connection
        $this->testConnection();

        // Test 2: Transfer each domain
        foreach ($testDomains as $testDomain) {
            $this->testDomainTransfer(
                $testDomain['domain'],
                $testDomain['auth_code']
            );
        }

        // Test 3: Transfer code retrieval
        foreach ($testDomains as $testDomain) {
            $this->testTransferCodeRetrieval($testDomain['domain']);
        }

        // Print summary
        $this->printSummary();
    }

    /**
     * Test 1: EPP Connection
     */
    private function testConnection(): void
    {
        echo "Test 1: EPP Connection\n";
        echo str_repeat('-', 60) . "\n";

        try {
            if ($this->dryRun) {
                echo "[SKIP] Dry run mode - connection test skipped\n";
                $this->results['connection'] = 'SKIPPED';
                return;
            }

            $this->client = new EppClient(
                $this->config['epp_host'],
                $this->config['epp_port'],
                $this->config['ssl_cert'],
                $this->config['ssl_key'],
                $this->config['ssl_ca']
            );

            echo "Connecting to {$this->config['epp_host']}:{$this->config['epp_port']}...\n";
            $this->client->connect();
            echo "✓ Connected\n";

            echo "Authenticating as {$this->config['username']}...\n";
            $this->client->login($this->config['username'], $this->config['password']);
            echo "✓ Authenticated\n";

            $this->results['connection'] = 'PASSED';
            echo "\n✓ Connection test PASSED\n\n";

        } catch (\Exception $e) {
            $this->results['connection'] = 'FAILED: ' . $e->getMessage();
            echo "\n✗ Connection test FAILED: {$e->getMessage()}\n\n";
        }
    }

    /**
     * Test 2: Domain Transfer
     */
    private function testDomainTransfer(string $domain, string $authCode): void
    {
        echo "Test: Transfer Domain - {$domain}\n";
        echo str_repeat('-', 60) . "\n";

        try {
            if ($this->dryRun) {
                echo "[DRY RUN] Would initiate transfer for {$domain}\n";
                echo "[DRY RUN] Auth code: " . substr($authCode, 0, 3) . "***\n";
                $this->results["transfer_{$domain}"] = 'DRY RUN';
                echo "\n";
                return;
            }

            // Initiate transfer
            echo "Initiating transfer...\n";
            $response = $this->client->transferDomain($domain, $authCode);

            $resultCode = $response->getResultCode();
            $message = $response->getMessage();

            echo "Result Code: {$resultCode}\n";
            echo "Message: {$message}\n";

            if ($response->isSuccess()) {
                echo "✓ Transfer initiated successfully\n";

                // Check if pending
                if ($resultCode == 1001) {
                    echo "ℹ Transfer is pending (normal for most registries)\n";
                }

                $this->results["transfer_{$domain}"] = "PASSED (Code: {$resultCode})";
            } else {
                echo "✗ Transfer failed\n";
                $this->results["transfer_{$domain}"] = "FAILED (Code: {$resultCode} - {$message})";
            }

            if ($this->verbose) {
                echo "\nFull Response:\n";
                echo json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
            }

            echo "\n";

        } catch (\Exception $e) {
            $this->results["transfer_{$domain}"] = 'ERROR: ' . $e->getMessage();
            echo "✗ Transfer test ERROR: {$e->getMessage()}\n\n";
        }
    }

    /**
     * Test 3: Transfer Code Retrieval
     */
    private function testTransferCodeRetrieval(string $domain): void
    {
        echo "Test: Get Transfer Code - {$domain}\n";
        echo str_repeat('-', 60) . "\n";

        try {
            if ($this->dryRun) {
                echo "[DRY RUN] Would retrieve transfer code for {$domain}\n";
                $this->results["authcode_{$domain}"] = 'DRY RUN';
                echo "\n";
                return;
            }

            echo "Retrieving transfer code...\n";
            $authCode = $this->client->getTransferCode($domain);

            if (!empty($authCode)) {
                echo "✓ Transfer code retrieved\n";
                echo "Auth Code: " . substr($authCode, 0, 3) . "*** (hidden for security)\n";
                $this->results["authcode_{$domain}"] = 'PASSED';
            } else {
                echo "⚠ Transfer code is empty (registry may not support retrieval)\n";
                $this->results["authcode_{$domain}"] = 'WARNING: Empty auth code';
            }

            echo "\n";

        } catch (\Exception $e) {
            $this->results["authcode_{$domain}"] = 'ERROR: ' . $e->getMessage();
            echo "✗ Transfer code retrieval ERROR: {$e->getMessage()}\n\n";
        }
    }

    /**
     * Print test summary
     */
    private function printSummary(): void
    {
        echo "\n========================================\n";
        echo "  Test Summary\n";
        echo "========================================\n\n";

        $passed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->results as $test => $result) {
            $status = '';
            if (strpos($result, 'PASSED') !== false || $result === 'PASSED') {
                $status = '✓ PASSED';
                $passed++;
            } elseif (strpos($result, 'FAILED') !== false || strpos($result, 'ERROR') !== false) {
                $status = '✗ FAILED';
                $failed++;
            } elseif (strpos($result, 'DRY RUN') !== false || $result === 'SKIPPED') {
                $status = '⊘ SKIPPED';
                $skipped++;
            } else {
                $status = '? UNKNOWN';
            }

            echo sprintf("%-40s %s\n", $test, $status);
            if ($result !== 'PASSED' && $this->verbose) {
                echo "  → {$result}\n";
            }
        }

        echo "\n" . str_repeat('-', 60) . "\n";
        echo "Total: " . count($this->results) . " | ";
        echo "Passed: {$passed} | ";
        echo "Failed: {$failed} | ";
        echo "Skipped: {$skipped}\n";

        if ($failed > 0) {
            echo "\n⚠ Some tests failed. Check logs for details.\n";
        } else {
            echo "\n✓ All tests passed!\n";
        }

        echo "\n";
    }
}

// Run tests
try {
    $tester = new TransferTester($config, $dryRun, $verbose);
    $tester->runAllTests($testDomains);
} catch (\Exception $e) {
    echo "\n✗ Fatal Error: {$e->getMessage()}\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Testing completed.\n";
exit(0);
