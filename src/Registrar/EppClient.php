<?php

/**
 * EPP Client - TLS Encrypted Connection Handler
 * 
 * Production-ready EPP client implementing RFC 5734 with TLS support
 * 
 * @package NixiEpp
 * @version 1.0.0
 */

namespace Box\Mod\Servicedomain\Registrar\NixiEpp;

class EppClient
{
    /**
     * Socket resource
     */
    private $socket = null;

    /**
     * Configuration
     */
    private array $config;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Connection state
     */
    private bool $connected = false;
    private bool $authenticated = false;

    /**
     * EPP Frame handling
     */
    private EppFrame $frameHandler;

    /**
     * Constructor
     */
    public function __construct(array $config, $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->frameHandler = new EppFrame();
    }

    /**
     * Connect to EPP server with TLS
     */
    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        $host = $this->config['host'];
        $port = $this->config['port'];
        $timeout = $this->config['timeout'] ?? 30;

        // Create SSL context
        $context = $this->createSslContext();

        // Connect with TLS
        $this->socket = stream_socket_client(
            "tls://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \Exception("Failed to connect to EPP server: {$errstr} ({$errno})");
        }

        // Set stream options
        stream_set_timeout($this->socket, $timeout);
        stream_set_blocking($this->socket, true);

        $this->connected = true;
        $this->log('Connected to EPP server: ' . $host . ':' . $port);

        // Read greeting
        $greeting = $this->readFrame();
        $this->log('Received server greeting');

        return true;
    }

    /**
     * Disconnect from EPP server
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
            $this->authenticated = false;
            $this->log('Disconnected from EPP server');
        }
    }

    /**
     * Login to EPP server
     */
    public function login(): bool
    {
        if ($this->authenticated) {
            return true;
        }

        $username = $this->config['username'];
        $password = $this->config['password'];
        $newPassword = $this->config['new_password'] ?? null;

        $loginXml = $this->frameHandler->createLogin(
            $username,
            $password,
            $newPassword
        );

        $response = $this->sendCommand($loginXml);
        
        if ($response->isSuccess()) {
            $this->authenticated = true;
            $this->log('Successfully authenticated');
            return true;
        }

        throw new \Exception('Login failed: ' . $response->getMessage());
    }

    /**
     * Logout from EPP server
     */
    public function logout(): bool
    {
        $logoutXml = $this->frameHandler->createLogout();
        $response = $this->sendCommand($logoutXml);
        
        $this->authenticated = false;
        $this->log('Logged out');
        
        return $response->isSuccess();
    }

    /**
     * Check domain availability
     */
    public function checkDomain(string $domainName): array
    {
        $checkXml = $this->frameHandler->createDomainCheck([$domainName]);
        $response = $this->sendCommand($checkXml);

        return $response->getDomainCheckResult($domainName);
    }

    /**
     * Create domain
     */
    public function createDomain(
        string $domainName,
        string $contactId,
        array $nameservers,
        int $period = 1,
        array $domainData = []
    ): EppResponse {
        $createXml = $this->frameHandler->createDomainCreate(
            $domainName,
            $contactId,
            $nameservers,
            $period,
            $domainData
        );

        return $this->sendCommand($createXml);
    }

    /**
     * Transfer domain
     */
    public function transferDomain(string $domainName, string $authCode, array $domainData = []): EppResponse
    {
        $transferXml = $this->frameHandler->createDomainTransfer(
            $domainName,
            $authCode,
            $domainData
        );

        return $this->sendCommand($transferXml);
    }

    /**
     * Renew domain
     */
    public function renewDomain(string $domainName, int $period = 1): EppResponse
    {
        $renewXml = $this->frameHandler->createDomainRenew($domainName, $period);
        return $this->sendCommand($renewXml);
    }

    /**
     * Get domain information
     */
    public function infoDomain(string $domainName): array
    {
        $infoXml = $this->frameHandler->createDomainInfo($domainName);
        $response = $this->sendCommand($infoXml);

        return $response->getDomainInfo();
    }

    /**
     * Update domain nameservers
     */
    public function updateDomainNs(string $domainName, array $nameservers): EppResponse
    {
        $updateXml = $this->frameHandler->createDomainUpdate($domainName, $nameservers);
        return $this->sendCommand($updateXml);
    }

    /**
     * Get transfer code (Auth Code)
     */
    public function getTransferCode(string $domainName): string
    {
        $infoXml = $this->frameHandler->createDomainInfo($domainName);
        $response = $this->sendCommand($infoXml);

        return $response->getAuthCode();
    }

    /**
     * Update domain status (add/remove status flags)
     */
    public function updateDomainStatus(
        string $domainName,
        array $addStatuses = [],
        array $removeStatuses = []
    ): EppResponse {
        $updateXml = $this->frameHandler->createDomainUpdateStatus(
            $domainName,
            $addStatuses,
            $removeStatuses
        );
        return $this->sendCommand($updateXml);
    }

    /**
     * Lock domain (add clientTransferProhibited status)
     */
    public function lockDomain(string $domainName): EppResponse
    {
        $updateXml = $this->frameHandler->createDomainUpdateStatus(
            $domainName,
            ['clientTransferProhibited'],
            []
        );
        return $this->sendCommand($updateXml);
    }

    /**
     * Unlock domain (remove clientTransferProhibited status)
     */
    public function unlockDomain(string $domainName): EppResponse
    {
        $updateXml = $this->frameHandler->createDomainUpdateStatus(
            $domainName,
            [],
            ['clientTransferProhibited']
        );
        return $this->sendCommand($updateXml);
    }

    /**
     * Enable DNSSEC for domain
     */
    public function enableDNSSEC(string $domainName, array $dsData): EppResponse
    {
        $updateXml = $this->frameHandler->createDomainUpdateDNSSEC(
            $domainName,
            $dsData,
            [],
            false
        );
        return $this->sendCommand($updateXml);
    }

    /**
     * Disable DNSSEC for domain (remove all)
     */
    public function disableDNSSEC(string $domainName): EppResponse
    {
        $updateXml = $this->frameHandler->createDomainUpdateDNSSEC(
            $domainName,
            [],
            [],
            true
        );
        return $this->sendCommand($updateXml);
    }

    /**
     * Update DNSSEC records
     */
    public function updateDNSSEC(string $domainName, array $addDsData = [], array $removeDsData = []): EppResponse
    {
        $updateXml = $this->frameHandler->createDomainUpdateDNSSEC(
            $domainName,
            $addDsData,
            $removeDsData,
            false
        );
        return $this->sendCommand($updateXml);
    }

    /**
     * Create glue record (host with IP addresses)
     */
    public function createGlueRecord(string $hostName, array $ipAddresses = []): EppResponse
    {
        $createXml = $this->frameHandler->createHostCreate($hostName, $ipAddresses);
        return $this->sendCommand($createXml);
    }

    /**
     * Update glue record (change IP addresses)
     */
    public function updateGlueRecord(string $hostName, array $addIps = [], array $removeIps = []): EppResponse
    {
        $updateXml = $this->frameHandler->createHostUpdate($hostName, $addIps, $removeIps);
        return $this->sendCommand($updateXml);
    }

    /**
     * Delete glue record
     */
    public function deleteGlueRecord(string $hostName): EppResponse
    {
        $deleteXml = $this->frameHandler->createHostDelete($hostName);
        return $this->sendCommand($deleteXml);
    }

    /**
     * Get glue record info
     */
    public function infoGlueRecord(string $hostName): array
    {
        $infoXml = $this->frameHandler->createHostInfo($hostName);
        $response = $this->sendCommand($infoXml);
        
        return $response->getHostInfo() ?? [];
    }

    /**
     * Enable privacy protection
     */
    public function enablePrivacyProtection(string $domainName): EppResponse
    {
        // Implementation depends on registry-specific extensions
        $updateXml = $this->frameHandler->createPrivacyProtectionUpdate($domainName, true);
        return $this->sendCommand($updateXml);
    }

    /**
     * Disable privacy protection
     */
    public function disablePrivacyProtection(string $domainName): EppResponse
    {
        $updateXml = $this->frameHandler->createPrivacyProtectionUpdate($domainName, false);
        return $this->sendCommand($updateXml);
    }

    /**
     * Delete domain
     */
    public function deleteDomain(string $domainName): EppResponse
    {
        $deleteXml = $this->frameHandler->createDomainDelete($domainName);
        return $this->sendCommand($deleteXml);
    }

    /**
     * Create contact
     */
    public function createContact(string $contactId, array $contactData): string
    {
        $createXml = $this->frameHandler->createContactCreate($contactId, $contactData);
        $response = $this->sendCommand($createXml);

        return $contactId;
    }

    /**
     * Get contact information
     */
    public function infoContact(string $contactId): array
    {
        $infoXml = $this->frameHandler->createContactInfo($contactId);
        $response = $this->sendCommand($infoXml);

        return $response->getContactInfo();
    }

    /**
     * Send EPP command and receive response
     */
    private function sendCommand(string $xml): EppResponse
    {
        if (!$this->connected) {
            throw new \Exception('Not connected to EPP server');
        }

        $this->log('Sending: ' . substr($xml, 0, 200) . '...');

        // Write frame
        $this->writeFrame($xml);

        // Read response
        $responseXml = $this->readFrame();
        $this->log('Received: ' . substr($responseXml, 0, 200) . '...');

        return new EppResponse($responseXml);
    }

    /**
     * Write EPP frame to socket
     */
    private function writeFrame(string $xml): void
    {
        $frame = $this->frameHandler->encodeFrame($xml);
        
        $written = fwrite($this->socket, $frame);
        if ($written === false || $written !== strlen($frame)) {
            throw new \Exception('Failed to write EPP frame');
        }

        fflush($this->socket);
    }

    /**
     * Read EPP frame from socket
     */
    private function readFrame(): string
    {
        // Read header (4 bytes)
        $header = fread($this->socket, 4);
        if ($header === false || strlen($header) !== 4) {
            throw new \Exception('Failed to read EPP frame header');
        }

        // Unpack frame length
        $length = unpack('N', $header)[1];
        
        if ($length < 4 || $length > 1048576) { // Max 1MB
            throw new \Exception('Invalid frame length: ' . $length);
        }

        // Read XML data
        $xmlLength = $length - 4;
        $xml = '';
        $bytesRead = 0;

        while ($bytesRead < $xmlLength) {
            $chunk = fread($this->socket, $xmlLength - $bytesRead);
            if ($chunk === false) {
                throw new \Exception('Failed to read EPP frame data');
            }
            
            $xml .= $chunk;
            $bytesRead += strlen($chunk);
        }

        return $xml;
    }

    /**
     * Create SSL/TLS context
     */
    private function createSslContext() // : resource (PHP 7.x/8.x stream context)
    {
        $contextOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'capture_peer_cert' => false,
            ],
        ];

        // Client certificate
        if (!empty($this->config['ssl_cert_path'])) {
            if (!file_exists($this->config['ssl_cert_path'])) {
                throw new \Exception('SSL certificate file not found: ' . $this->config['ssl_cert_path']);
            }
            $contextOptions['ssl']['local_cert'] = $this->config['ssl_cert_path'];
        }

        // Client private key
        if (!empty($this->config['ssl_key_path'])) {
            if (!file_exists($this->config['ssl_key_path'])) {
                throw new \Exception('SSL key file not found: ' . $this->config['ssl_key_path']);
            }
            $contextOptions['ssl']['local_pk'] = $this->config['ssl_key_path'];
        }

        // CA certificate
        if (!empty($this->config['ssl_ca_path'])) {
            if (!file_exists($this->config['ssl_ca_path'])) {
                throw new \Exception('CA certificate file not found: ' . $this->config['ssl_ca_path']);
            }
            $contextOptions['ssl']['cafile'] = $this->config['ssl_ca_path'];
        }

        return stream_context_create($contextOptions);
    }

    /**
     * Log message
     */
    private function log(string $message): void
    {
        if ($this->logger) {
            $this->logger->info('[EPP] ' . $message);
        }
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Check if authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
