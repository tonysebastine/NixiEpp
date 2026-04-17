<?php

/**
 * EPP Response Handler
 * 
 * Parses and handles EPP XML responses
 * 
 * @package NixiEpp
 * @version 1.2.0
 */

namespace Box\Mod\Servicedomain\Registrar\NixiEpp;

class EppResponse
{
    /**
     * SimpleXML object
     */
    private ?\SimpleXMLElement $xml;

    /**
     * Response code
     */
    private int $resultCode = 0;

    /**
     * Response message
     */
    private string $message = '';

    /**
     * Response data
     */
    private array $data = [];

    /**
     * Constructor
     */
    public function __construct(string $xmlString)
    {
        $this->xml = simplexml_load_string($xmlString);
        $this->parseResponse();
    }

    /**
     * Parse EPP response
     */
    private function parseResponse(): void
    {
        if (!$this->xml) {
            throw new \Exception('Invalid EPP response XML');
        }

        // Register namespaces
        $this->xml->registerXPathNamespace('epp', 'urn:ietf:params:xml:ns:epp-1.0');
        $this->xml->registerXPathNamespace('domain', 'urn:ietf:params:xml:ns:domain-1.0');
        $this->xml->registerXPathNamespace('contact', 'urn:ietf:params:xml:ns:contact-1.0');
        $this->xml->registerXPathNamespace('host', 'urn:ietf:params:xml:ns:host-1.0');

        // Parse result code and message
        $result = $this->xml->xpath('//epp:response/epp:result');
        if (isset($result[0])) {
            $this->resultCode = (int) $result[0]['code'];
            $msg = $result[0]->xpath('epp:msg');
            $this->message = (string) ($msg[0] ?? 'Unknown error');
        }

        // Parse response data
        $resData = $this->xml->xpath('//epp:response/epp:resData');
        if (isset($resData[0])) {
            $this->data = $this->xmlToArray($resData[0]);
        }
    }

    /**
     * Check if response is successful
     */
    public function isSuccess(): bool
    {
        return $this->resultCode >= 1000 && $this->resultCode < 2000;
    }

    /**
     * Get result code
     */
    public function getResultCode(): int
    {
        return $this->resultCode;
    }

    /**
     * Get message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get response data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get domain check result
     */
    public function getDomainCheckResult(string $domainName): array
    {
        $cd = $this->xml->xpath('//domain:cd');
        if (!isset($cd[0])) {
            return ['available' => false, 'reason' => 'No check data'];
        }

        $nameNode = $cd[0]->xpath('domain:name');
        if (!isset($nameNode[0])) {
            return ['available' => false, 'reason' => 'No name node'];
        }

        $name = (string) $nameNode[0];
        $available = isset($nameNode[0]['avail']) && (string) $nameNode[0]['avail'] === '1';
        $reason = '';

        if (!$available) {
            $reasonNode = $cd[0]->xpath('domain:reason');
            $reason = (string) ($reasonNode[0] ?? 'Unknown');
        }

        return [
            'domain' => $name,
            'available' => $available,
            'reason' => $reason,
        ];
    }

    /**
     * Get domain information
     */
    public function getDomainInfo(): array
    {
        $infData = $this->xml->xpath('//domain:infData');
        if (!isset($infData[0])) {
            return [];
        }

        $info = $infData[0];
        
        return [
            'name' => (string) ($info->xpath('domain:name')[0] ?? ''),
            'roid' => (string) ($info->xpath('domain:roid')[0] ?? ''),
            'status' => $this->parseStatus($info->xpath('domain:status')),
            'registrant' => (string) ($info->xpath('domain:registrant')[0] ?? ''),
            'contacts' => $this->parseContacts($info->xpath('domain:contact')),
            'nameservers' => $this->parseNameservers($info->xpath('domain:ns')),
            'host' => $this->parseHosts($info->xpath('domain:host')),
            'clID' => (string) ($info->xpath('domain:clID')[0] ?? ''),
            'crID' => (string) ($info->xpath('domain:crID')[0] ?? ''),
            'crDate' => (string) ($info->xpath('domain:crDate')[0] ?? ''),
            'upID' => (string) ($info->xpath('domain:upID')[0] ?? ''),
            'upDate' => (string) ($info->xpath('domain:upDate')[0] ?? ''),
            'exDate' => (string) ($info->xpath('domain:exDate')[0] ?? ''),
            'trDate' => (string) ($info->xpath('domain:trDate')[0] ?? ''),
            'authInfo' => (string) ($info->xpath('domain:authInfo/domain:pw')[0] ?? ''),
        ];
    }

    /**
     * Get authorization code
     */
    public function getAuthCode(): string
    {
        $authInfo = $this->xml->xpath('//domain:authInfo/domain:pw');
        if (isset($authInfo[0])) {
            return (string) $authInfo[0];
        }

        return '';
    }

    /**
     * Get contact information
     */
    public function getContactInfo(): array
    {
        $infData = $this->xml->xpath('//contact:infData');
        if (!isset($infData[0])) {
            return [];
        }

        $info = $infData[0];
        
        return [
            'id' => (string) ($info->xpath('contact:id')[0] ?? ''),
            'roid' => (string) ($info->xpath('contact:roid')[0] ?? ''),
            'status' => $this->parseStatus($info->xpath('contact:status')),
            'postalInfo' => $this->parsePostalInfo($info->xpath('contact:postalInfo')),
            'voice' => (string) ($info->xpath('contact:voice')[0] ?? ''),
            'fax' => (string) ($info->xpath('contact:fax')[0] ?? ''),
            'email' => (string) ($info->xpath('contact:email')[0] ?? ''),
            'clID' => (string) ($info->xpath('contact:clID')[0] ?? ''),
            'crID' => (string) ($info->xpath('contact:crID')[0] ?? ''),
            'crDate' => (string) ($info->xpath('contact:crDate')[0] ?? ''),
            'upID' => (string) ($info->xpath('contact:upID')[0] ?? ''),
            'upDate' => (string) ($info->xpath('contact:upDate')[0] ?? ''),
            'trDate' => (string) ($info->xpath('contact:trDate')[0] ?? ''),
            'authInfo' => (string) ($info->xpath('contact:authInfo/contact:pw')[0] ?? ''),
        ];
    }

    /**
     * Parse status array
     */
    private function parseStatus(array $statusNodes): array
    {
        $statuses = [];
        foreach ($statusNodes as $node) {
            $statuses[] = [
                'status' => (string) $node['s'],
                'language' => (string) ($node['lang'] ?? 'en'),
                'message' => (string) $node,
            ];
        }
        return $statuses;
    }

    /**
     * Parse contacts
     */
    private function parseContacts(array $contactNodes): array
    {
        $contacts = [];
        foreach ($contactNodes as $node) {
            $contacts[] = [
                'id' => (string) $node,
                'type' => (string) ($node['type'] ?? ''),
            ];
        }
        return $contacts;
    }

    /**
     * Parse nameservers
     */
    private function parseNameservers(?\SimpleXMLElement $nsNode): array
    {
        if (!$nsNode || !isset($nsNode[0])) {
            return [];
        }

        $nameservers = [];
        $hostObjects = $nsNode[0]->xpath('domain:hostObj');
        foreach ($hostObjects as $host) {
            $nameservers[] = (string) $host;
        }

        return $nameservers;
    }

    /**
     * Parse hosts
     */
    private function parseHosts(array $hostNodes): array
    {
        $hosts = [];
        foreach ($hostNodes as $node) {
            $hosts[] = (string) $node;
        }
        return $hosts;
    }

    /**
     * Parse postal info
     */
    private function parsePostalInfo(array $postalNodes): array
    {
        $postalInfo = [];
        foreach ($postalNodes as $node) {
            $postalInfo[] = [
                'type' => (string) ($node['type'] ?? 'loc'),
                'name' => (string) ($node->xpath('contact:name')[0] ?? ''),
                'org' => (string) ($node->xpath('contact:org')[0] ?? ''),
                'street' => [],
                'city' => (string) ($node->xpath('contact:city')[0] ?? ''),
                'sp' => (string) ($node->xpath('contact:sp')[0] ?? ''),
                'pc' => (string) ($node->xpath('contact:pc')[0] ?? ''),
                'cc' => (string) ($node->xpath('contact:cc')[0] ?? ''),
            ];

            $streets = $node->xpath('contact:street');
            foreach ($streets as $street) {
                $postalInfo[count($postalInfo) - 1]['street'][] = (string) $street;
            }
        }
        return $postalInfo;
    }

    /**
     * Convert SimpleXML to array
     */
    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $array = [];
        foreach ($xml->children() as $child) {
            $name = $child->getName();
            if (count($child->children()) > 0) {
                $array[$name] = $this->xmlToArray($child);
            } else {
                $array[$name] = (string) $child;
            }
        }
        return $array;
    }

    /**
     * Get host (glue record) information from response
     */
    public function getHostInfo(): ?array
    {
        $info = $this->xml->xpath('//host:infData');
        if (!isset($info[0])) {
            return null;
        }

        $info = $info[0];
        
        // Parse IP addresses
        $ipAddresses = [];
        $addrNodes = $info->xpath('host:addresses/host:addr');
        foreach ($addrNodes as $addr) {
            $ipAddresses[] = [
                'ip' => (string) $addr,
                'version' => (string) ($addr['ip'] ?? 'v4'),
            ];
        }

        return [
            'name' => (string) ($info->xpath('host:name')[0] ?? ''),
            'status' => $this->parseStatus($info->xpath('host:status') ?? []),
            'ipAddresses' => $ipAddresses,
            'clID' => (string) ($info->xpath('host:clID')[0] ?? ''),
            'crID' => (string) ($info->xpath('host:crID')[0] ?? ''),
            'crDate' => (string) ($info->xpath('host:crDate')[0] ?? ''),
            'upID' => (string) ($info->xpath('host:upID')[0] ?? ''),
            'upDate' => (string) ($info->xpath('host:upDate')[0] ?? ''),
            'trDate' => (string) ($info->xpath('host:trDate')[0] ?? ''),
        ];
    }

    /**
     * Get raw XML
     */
    public function getXml(): string
    {
        return $this->xml->asXML();
    }
}
