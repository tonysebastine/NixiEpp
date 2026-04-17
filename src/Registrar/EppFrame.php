<?php

/**
 * EPP Frame Handler - XML Request Builder
 * 
 * Creates EPP-compliant XML requests according to RFC 5730-5734
 * 
 * @package NixiEpp
 * @version 1.2.0
 */

namespace Box\Mod\Servicedomain\Registrar\NixiEpp;

class EppFrame
{
    /**
     * EPP Namespaces
     */
    const NS_EPP = 'urn:ietf:params:xml:ns:epp-1.0';
    const NS_DOMAIN = 'urn:ietf:params:xml:ns:domain-1.0';
    const NS_CONTACT = 'urn:ietf:params:xml:ns:contact-1.0';
    const NS_HOST = 'urn:ietf:params:xml:ns:host-1.0';
    const NS_SEC_DNS = 'urn:ietf:params:xml:ns:secDNS-1.1';

    /**
     * Client transaction ID
     */
    private string $clTRID;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->generateClTRID();
    }

    /**
     * Generate client transaction ID
     */
    private function generateClTRID(): void
    {
        $this->clTRID = uniqid('NIXI-', true);
    }

    /**
     * Encode XML into EPP frame with length header
     */
    public function encodeFrame(string $xml): string
    {
        $xmlLength = strlen($xml);
        $totalLength = $xmlLength + 4; // 4 bytes for length header

        // Pack total length as big-endian 32-bit integer
        $header = pack('N', $totalLength);

        return $header . $xml;
    }

    /**
     * Create EPP envelope
     */
    private function createEnvelope(string $command): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="' . self::NS_EPP . '">
    ' . $command . '
</epp>';
    }

    /**
     * Create login command
     */
    public function createLogin(string $username, string $password, ?string $newPassword = null): string
    {
        $passwordChange = '';
        if ($newPassword) {
            $passwordChange = '<newPW>' . htmlspecialchars($newPassword) . '</newPW>';
        }

        $command = '<command>
    <login>
        <clID>' . htmlspecialchars($username) . '</clID>
        <pw>' . htmlspecialchars($password) . '</pw>
        ' . $passwordChange . '
        <options>
            <version>1.0</version>
            <lang>en</lang>
        </options>
        <svcs>
            <objURI>' . self::NS_DOMAIN . '</objURI>
            <objURI>' . self::NS_CONTACT . '</objURI>
            <objURI>' . self::NS_HOST . '</objURI>
            <svcExtension>
                <extURI>' . self::NS_SEC_DNS . '</extURI>
            </svcExtension>
        </svcs>
    </login>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create logout command
     */
    public function createLogout(): string
    {
        $command = '<command>
    <logout/>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain check command
     */
    public function createDomainCheck(array $domains): string
    {
        $namesXml = '';
        foreach ($domains as $domain) {
            $namesXml .= '<name>' . htmlspecialchars($domain) . '</name>';
        }

        $command = '<command>
    <check>
        <domain:check xmlns:domain="' . self::NS_DOMAIN . '">
            ' . $namesXml . '
        </domain:check>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain create command
     */
    public function createDomainCreate(
        string $domainName,
        string $contactId,
        array $nameservers,
        int $period = 1,
        array $domainData = []
    ): string {
        $nsXml = '';
        if (!empty($nameservers)) {
            $nsXml = '<domain:ns>';
            foreach ($nameservers as $ns) {
                $nsXml .= '<domain:hostObj>' . htmlspecialchars($ns) . '</domain:hostObj>';
            }
            $nsXml .= '</domain:ns>';
        }

        $authInfo = '';
        if (!empty($domainData['auth_code'])) {
            $authInfo = '<domain:authInfo><domain:pw>' . htmlspecialchars($domainData['auth_code']) . '</domain:pw></domain:authInfo>';
        }

        // DNSSEC extension
        $dnssecExtension = '';
        if (!empty($domainData['dnssec'])) {
            $dnssecExtension = $this->createDNSSECExtension($domainData['dnssec']);
        }

        $command = '<command>
    <create>
        <domain:create xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
            <domain:period unit="y">' . $period . '</domain:period>
            ' . $nsXml . '
            <domain:registrant>' . htmlspecialchars($contactId) . '</domain:registrant>
            <domain:contact type="admin">' . htmlspecialchars($contactId) . '</domain:contact>
            <domain:contact type="tech">' . htmlspecialchars($contactId) . '</domain:contact>
            ' . $authInfo . '
        </domain:create>
    </command>
    ' . $dnssecExtension . '
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain transfer command
     */
    public function createDomainTransfer(string $domainName, string $authCode, array $domainData = []): string
    {
        $period = $domainData['transfer_period'] ?? 1;

        $command = '<command>
    <transfer op="request">
        <domain:transfer xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
            <domain:period unit="y">' . $period . '</domain:period>
            <domain:authInfo>
                <domain:pw>' . htmlspecialchars($authCode) . '</domain:pw>
            </domain:authInfo>
        </domain:transfer>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain renew command
     */
    public function createDomainRenew(string $domainName, int $period = 1): string
    {
        $command = '<command>
    <renew>
        <domain:renew xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
            <domain:period unit="y">' . $period . '</domain:period>
        </domain:renew>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain info command
     */
    public function createDomainInfo(string $domainName): string
    {
        $command = '<command>
    <info>
        <domain:info xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
        </domain:info>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain update command (nameservers)
     */
    public function createDomainUpdate(string $domainName, array $nameservers): string
    {
        $addNs = '';
        foreach ($nameservers as $ns) {
            $addNs .= '<domain:hostObj>' . htmlspecialchars($ns) . '</domain:hostObj>';
        }

        $command = '<command>
    <update>
        <domain:update xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
            <domain:chg>
                <domain:ns>
                    ' . $addNs . '
                </domain:ns>
            </domain:chg>
        </domain:update>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain update status command (lock/unlock)
     */
    public function createDomainUpdateStatus(
        string $domainName,
        array $addStatuses = [],
        array $removeStatuses = []
    ): string {
        $addXml = '';
        foreach ($addStatuses as $status) {
            $addXml .= '<domain:status s="' . htmlspecialchars($status) . '"/>';
        }

        $removeXml = '';
        foreach ($removeStatuses as $status) {
            $removeXml .= '<domain:status s="' . htmlspecialchars($status) . '"/>';
        }

        $command = '<command>
    <update>
        <domain:update xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
            ' . ($addXml ? '<domain:add>' . $addXml . '</domain:add>' : '') . '
            ' . ($removeXml ? '<domain:rem>' . $removeXml . '</domain:rem>' : '') . '
        </domain:update>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create DNSSEC extension for domain commands
     * 
     * Supports both signed and unsigned delegation
     * 
     * @param array $dnssec DNSSEC data
     * @return string XML extension
     */
    public function createDNSSECExtension(array $dnssec): string
    {
        $dnssecXml = '<extension>';
        $dnssecXml .= '<secDNS:create xmlns:secDNS="' . self::NS_SEC_DNS . '">';

        // Check if using dsData (signed delegation) or keyData (unsigned)
        if (!empty($dnssec['dsData'])) {
            // DS record (signed delegation)
            foreach ($dnssec['dsData'] as $ds) {
                $dnssecXml .= '<secDNS:dsData>';
                $dnssecXml .= '<secDNS:keyTag>' . intval($ds['keyTag']) . '</sec:keyTag>';
                $dnssecXml .= '<secDNS:alg>' . intval($ds['algorithm']) . '</secDNS:alg>';
                $dnssecXml .= '<secDNS:digestType>' . intval($ds['digestType']) . '</secDNS:digestType>';
                $dnssecXml .= '<secDNS:digest>' . htmlspecialchars($ds['digest']) . '</secDNS:digest>';
                
                // Optional maxSigLife
                if (!empty($ds['maxSigLife'])) {
                    $dnssecXml .= '<secDNS:maxSigLife>' . intval($ds['maxSigLife']) . '</secDNS:maxSigLife>';
                }
                
                $dnssecXml .= '</secDNS:dsData>';
            }
        } elseif (!empty($dnssec['keyData'])) {
            // DNSKEY record (unsigned delegation)
            foreach ($dnssec['keyData'] as $key) {
                $dnssecXml .= '<secDNS:keyData>';
                $dnssecXml .= '<secDNS:flags>' . intval($key['flags']) . '</secDNS:flags>';
                $dnssecXml .= '<secDNS:protocol>' . intval($key['protocol']) . '</secDNS:protocol>';
                $dnssecXml .= '<secDNS:alg>' . intval($key['algorithm']) . '</secDNS:alg>';
                $dnssecXml .= '<secDNS:pubKey>' . htmlspecialchars($key['publicKey']) . '</secDNS:pubKey>';
                
                $dnssecXml .= '</secDNS:keyData>';
            }
        }

        // Optional: remove existing DNSSEC first
        if (!empty($dnssec['remove'])) {
            $dnssecXml = '<extension>';
            $dnssecXml .= '<secDNS:update xmlns:secDNS="' . self::NS_SEC_DNS . '">';
            $dnssecXml .= '<secDNS:rem>'; 
            $dnssecXml .= '<secDNS:all>true</secDNS:all>';
            $dnssecXml .= '</secDNS:rem>';
            $dnssecXml .= '</secDNS:update>';
            $dnssecXml .= '</extension>';
            return $dnssecXml;
        }

        $dnssecXml .= '</secDNS:create>';
        $dnssecXml .= '</extension>';

        return $dnssecXml;
    }

    /**
     * Create host (glue record) create command
     * 
     * Glue records are nameservers with IP addresses for in-bailiwick nameservers
     * 
     * @param string $hostName Host name (e.g., ns1.example.com)
     * @param array $ipAddresses IP addresses (IPv4 and/or IPv6)
     * @return string XML command
     */
    public function createHostCreate(string $hostName, array $ipAddresses = []): string
    {
        $addrXml = '';
        if (!empty($ipAddresses)) {
            foreach ($ipAddresses as $ip) {
                // Detect IP version
                $version = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'v6' : 'v4';
                $addrXml .= '<host:addr ip="' . $version . '">' . htmlspecialchars($ip) . '</host:addr>';
            }
        }

        $command = '<command>
    <create>
        <host:create xmlns:host="' . self::NS_HOST . '">
            <host:name>' . htmlspecialchars($hostName) . '</host:name>
            ' . $addrXml . '
        </host:create>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create host update command (change IP addresses)
     */
    public function createHostUpdate(string $hostName, array $addIps = [], array $removeIps = []): string
    {
        $addXml = '';
        if (!empty($addIps)) {
            $addXml = '<host:add>';
            foreach ($addIps as $ip) {
                $version = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'v6' : 'v4';
                $addXml .= '<host:addr ip="' . $version . '">' . htmlspecialchars($ip) . '</host:addr>';
            }
            $addXml .= '</host:add>';
        }

        $remXml = '';
        if (!empty($removeIps)) {
            $remXml = '<host:rem>';
            foreach ($removeIps as $ip) {
                $version = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'v6' : 'v4';
                $remXml .= '<host:addr ip="' . $version . '">' . htmlspecialchars($ip) . '</host:addr>';
            }
            $remXml .= '</host:rem>';
        }

        $command = '<command>
    <update>
        <host:update xmlns:host="' . self::NS_HOST . '">
            <host:name>' . htmlspecialchars($hostName) . '</host:name>
            ' . $addXml . '
            ' . $remXml . '
        </host:update>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create host delete command
     */
    public function createHostDelete(string $hostName): string
    {
        $command = '<command>
    <delete>
        <host:delete xmlns:host="' . self::NS_HOST . '">
            <host:name>' . htmlspecialchars($hostName) . '</host:name>
        </host:delete>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create host info command
     */
    public function createHostInfo(string $hostName): string
    {
        $command = '<command>
    <info>
        <host:info xmlns:host="' . self::NS_HOST . '">
            <host:name>' . htmlspecialchars($hostName) . '</host:name>
        </host:info>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain update with DNSSEC add
     */
    public function createDomainUpdateDNSSEC(string $domainName, array $addDsData = [], array $removeDsData = [], bool $removeAll = false): string
    {
        $dnssecXml = '<extension>';
        $dnssecXml .= '<secDNS:update xmlns:secDNS="' . self::NS_SEC_DNS . '">';

        // Remove all existing DNSSEC
        if ($removeAll) {
            $dnssecXml .= '<secDNS:rem>';
            $dnssecXml .= '<secDNS:all>true</secDNS:all>';
            $dnssecXml .= '</secDNS:rem>';
        }

        // Remove specific DS records
        if (!empty($removeDsData)) {
            $dnssecXml .= '<secDNS:rem>';
            foreach ($removeDsData as $ds) {
                $dnssecXml .= '<secDNS:dsData>';
                $dnssecXml .= '<secDNS:keyTag>' . intval($ds['keyTag']) . '</secDNS:keyTag>';
                $dnssecXml .= '<secDNS:alg>' . intval($ds['algorithm']) . '</secDNS:alg>';
                $dnssecXml .= '<secDNS:digestType>' . intval($ds['digestType']) . '</secDNS:digestType>';
                $dnssecXml .= '<secDNS:digest>' . htmlspecialchars($ds['digest']) . '</secDNS:digest>';
                $dnssecXml .= '</secDNS:dsData>';
            }
            $dnssecXml .= '</secDNS:rem>';
        }

        // Add new DS records
        if (!empty($addDsData)) {
            $dnssecXml .= '<secDNS:add>';
            foreach ($addDsData as $ds) {
                $dnssecXml .= '<secDNS:dsData>';
                $dnssecXml .= '<secDNS:keyTag>' . intval($ds['keyTag']) . '</secDNS:keyTag>';
                $dnssecXml .= '<secDNS:alg>' . intval($ds['algorithm']) . '</secDNS:alg>';
                $dnssecXml .= '<secDNS:digestType>' . intval($ds['digestType']) . '</secDNS:digestType>';
                $dnssecXml .= '<secDNS:digest>' . htmlspecialchars($ds['digest']) . '</secDNS:digest>';
                
                if (!empty($ds['maxSigLife'])) {
                    $dnssecXml .= '<secDNS:maxSigLife>' . intval($ds['maxSigLife']) . '</secDNS:maxSigLife>';
                }
                
                $dnssecXml .= '</secDNS:dsData>';
            }
            $dnssecXml .= '</secDNS:add>';
        }

        $dnssecXml .= '</secDNS:update>';
        $dnssecXml .= '</extension>';

        $command = '<command>
    <update>
        <domain:update xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
        </domain:update>
    </command>
    ' . $dnssecXml . '
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create domain delete command
     */
    public function createDomainDelete(string $domainName): string
    {
        $command = '<command>
    <delete>
        <domain:delete xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
        </domain:delete>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create contact create command
     */
    public function createContactCreate(string $contactId, array $contactData): string
    {
        $postalInfo = $this->createPostalInfo($contactData);
        
        $command = '<command>
    <create>
        <contact:create xmlns:contact="' . self::NS_CONTACT . '">
            <contact:id>' . htmlspecialchars($contactId) . '</contact:id>
            ' . $postalInfo . '
            <contact:voice>' . htmlspecialchars($contactData['phone'] ?? '+1.0000000000') . '</contact:voice>
            <contact:fax>' . htmlspecialchars($contactData['fax'] ?? '') . '</contact:fax>
            <contact:email>' . htmlspecialchars($contactData['contact_email'] ?? '') . '</contact:email>
            <contact:authInfo>
                <contact:pw>' . htmlspecialchars($contactData['auth_code'] ?? uniqid()) . '</contact:pw>
            </contact:authInfo>
        </contact:create>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create contact info command
     */
    public function createContactInfo(string $contactId): string
    {
        $command = '<command>
    <info>
        <contact:info xmlns:contact="' . self::NS_CONTACT . '">
            <contact:id>' . htmlspecialchars($contactId) . '</contact:id>
        </contact:info>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }

    /**
     * Create postal info XML
     */
    private function createPostalInfo(array $contactData): string
    {
        $street1 = htmlspecialchars($contactData['address1'] ?? '');
        $street2 = !empty($contactData['address2']) ? '<contact:street>' . htmlspecialchars($contactData['address2']) . '</contact:street>' : '';
        $street3 = !empty($contactData['address3']) ? '<contact:street>' . htmlspecialchars($contactData['address3']) . '</contact:street>' : '';

        return '<contact:postalInfo type="loc">
            <contact:name>' . htmlspecialchars($contactData['contact_name'] ?? 'Unknown') . '</contact:name>
            <contact:org>' . htmlspecialchars($contactData['company'] ?? '') . '</contact:org>
            <contact:street>' . $street1 . '</contact:street>
            ' . $street2 . '
            ' . $street3 . '
            <contact:city>' . htmlspecialchars($contactData['city'] ?? '') . '</contact:city>
            <contact:sp>' . htmlspecialchars($contactData['state'] ?? '') . '</contact:sp>
            <contact:pc>' . htmlspecialchars($contactData['postcode'] ?? '') . '</contact:pc>
            <contact:cc>' . htmlspecialchars($contactData['country'] ?? 'US') . '</contact:cc>
        </contact:postalInfo>';
    }

    /**
     * Create privacy protection update (registry-specific extension)
     */
    public function createPrivacyProtectionUpdate(string $domainName, bool $enable): string
    {
        // This is registry-specific - implement according to your registry's extension
        $status = $enable ? '1' : '0';
        
        $command = '<command>
    <update>
        <domain:update xmlns:domain="' . self::NS_DOMAIN . '">
            <domain:name>' . htmlspecialchars($domainName) . '</domain:name>
        </domain:update>
        <extension>
            <!-- Registry-specific privacy protection extension -->
            <privacy:update xmlns:privacy="urn:example:privacy-1.0">
                <privacy:enabled>' . $status . '</privacy:enabled>
            </privacy:update>
        </extension>
    </command>
    <clTRID>' . $this->clTRID . '</clTRID>
</command>';

        $this->generateClTRID();
        return $this->createEnvelope($command);
    }
}
