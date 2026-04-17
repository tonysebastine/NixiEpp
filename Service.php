<?php

/**
 * NixiEpp Registrar Module for FOSSBilling
 * 
 * Production-ready EPP (Extensible Provisioning Protocol) registrar module
 * supporting TLS-encrypted connections for domain registration management.
 * 
 * @package NixiEpp
 * @version 1.0.0
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
            'version' => '1.0.0',
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

            $client->logout();
            $client->disconnect();

            $this->getLog()->info('Domain transfer initiated: ' . $domainName);
            return true;
        } catch (\Exception $e) {
            $this->getLog()->err('Domain transfer failed: ' . $e->getMessage());
            throw new \Exception('Domain transfer failed: ' . $e->getMessage());
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
