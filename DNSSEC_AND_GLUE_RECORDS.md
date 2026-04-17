# DNSSEC & Glue Records Guide - NixiEpp v1.2.0

## 📋 Overview

Complete support for DNSSEC (Domain Name System Security Extensions) and Glue Records (host objects with IP addresses).

### Features

✅ **DNSSEC Management**
- Enable DNSSEC on domains
- Disable DNSSEC
- Update DS records
- Add/remove DNSSEC during domain creation

✅ **Glue Records (Host Objects)**
- Create glue records with IPv4/IPv6
- Update glue record IPs
- Delete glue records
- Query glue record info

---

## 🔐 DNSSEC (Domain Name System Security Extensions)

### What is DNSSEC?

DNSSEC adds cryptographic signatures to DNS records to prevent:
- DNS spoofing
- Cache poisoning
- Man-in-the-middle attacks

### How It Works

```
Domain Owner
    ↓
Generates DNSKEY (public/private key pair)
    ↓
Creates DS record (hash of public key)
    ↓
Submits DS record to registry via EPP
    ↓
Registry stores DS record
    ↓
Resolvers validate DNS responses using DS → DNSKEY chain
```

---

## 🛠️ DNSSEC Operations

### 1. Enable DNSSEC on Existing Domain

**Method**: `EppClient::enableDNSSEC()`

**Example**:
```php
$client->enableDNSSEC('example.in', [
    [
        'keyTag' => 12345,
        'algorithm' => 13,      // ECDSAP256SHA256
        'digestType' => 2,      // SHA-256
        'digest' => 'ABC123DEF456...',
        'maxSigLife' => 86400,  // Optional
    ]
]);
```

**DS Record Fields**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `keyTag` | int | ✅ | Key tag (0-65535) |
| `algorithm` | int | ✅ | DNSSEC algorithm number |
| `digestType` | int | ✅ | Digest algorithm number |
| `digest` | string | ✅ | Hex digest of DNSKEY |
| `maxSigLife` | int | ❌ | Maximum signature lifetime (seconds) |

### Common Algorithms

| Algorithm | Number | Description |
|-----------|--------|-------------|
| RSASHA1 | 5 | RSA/SHA-1 |
| RSASHA256 | 8 | RSA/SHA-256 |
| RSASHA512 | 10 | RSA/SHA-512 |
| ECDSAP256SHA256 | 13 | ECDSA Curve P-256 with SHA-256 |
| ECDSAP384SHA384 | 14 | ECDSA Curve P-384 with SHA-384 |
| ED25519 | 15 | Ed25519 |
| ED448 | 16 | Ed448 |

### Common Digest Types

| Type | Number | Description |
|------|--------|-------------|
| SHA-1 | 1 | SHA-1 |
| SHA-256 | 2 | SHA-256 |
| SHA-384 | 4 | SHA-384 |

---

### 2. Disable DNSSEC

**Method**: `EppClient::disableDNSSEC()`

**Example**:
```php
$client->disableDNSSEC('example.in');
```

**Removes all DS records** from the domain.

---

### 3. Update DNSSEC Records

**Method**: `EppClient::updateDNSSEC()`

**Example - Add new DS record**:
```php
$client->updateDNSSEC('example.in', [
    // Add
    [
        'keyTag' => 12346,
        'algorithm' => 13,
        'digestType' => 2,
        'digest' => 'NEW789GHI012...',
    ]
], [
    // Remove (optional)
    [
        'keyTag' => 12345,
        'algorithm' => 13,
        'digestType' => 2,
        'digest' => 'ABC123DEF456...',
    ]
]);
```

---

### 4. DNSSEC During Domain Creation

**Method**: Include `dnssec` in domain data

**Example**:
```php
$domainData = [
    'auth_code' => 'secret123',
    'dnssec' => [
        'dsData' => [
            [
                'keyTag' => 12345,
                'algorithm' => 13,
                'digestType' => 2,
                'digest' => 'ABC123DEF456...',
            ]
        ]
    ]
];

$client->createDomain('example.in', $contactId, $nameservers, 1, $domainData);
```

---

## 🔗 Glue Records (Host Objects)

### What are Glue Records?

Glue records are **nameserver records with IP addresses** that resolve the "chicken-and-egg" problem:

```
Problem:
example.com uses ns1.example.com
But ns1.example.com is IN example.com
How to resolve ns1.example.com without knowing example.com's nameservers?

Solution: Glue Records
Registry stores ns1.example.com → 192.0.2.1
Now resolvers can find the nameserver!
```

### When Do You Need Glue Records?

✅ **Required**: When nameserver is **within** the domain it serves (in-bailiwick)
- `example.com` uses `ns1.example.com` → Need glue

❌ **Not Required**: When nameserver is **outside** the domain (out-of-bailiwick)
- `example.com` uses `ns1.google.com` → No glue needed

---

## 🛠️ Glue Record Operations

### 1. Create Glue Record

**Method**: `EppClient::createGlueRecord()`

**Example - IPv4 only**:
```php
$client->createGlueRecord('ns1.example.in', [
    '192.0.2.1',
    '192.0.2.2',
]);
```

**Example - IPv6 only**:
```php
$client->createGlueRecord('ns1.example.in', [
    '2001:db8::1',
]);
```

**Example - Dual stack (IPv4 + IPv6)**:
```php
$client->createGlueRecord('ns1.example.in', [
    '192.0.2.1',
    '2001:db8::1',
]);
```

---

### 2. Update Glue Record

**Method**: `EppClient::updateGlueRecord()`

**Example - Add IP**:
```php
$client->updateGlueRecord('ns1.example.in', [
    '192.0.2.3',  // New IP to add
], []);
```

**Example - Remove IP**:
```php
$client->updateGlueRecord('ns1.example.in', [], [
    '192.0.2.1',  // IP to remove
]);
```

**Example - Replace IPs**:
```php
$client->updateGlueRecord('ns1.example.in', [
    '192.0.2.10',
    '2001:db8::10',
], [
    '192.0.2.1',
    '192.0.2.2',
]);
```

---

### 3. Delete Glue Record

**Method**: `EppClient::deleteGlueRecord()`

**Example**:
```php
$client->deleteGlueRecord('ns1.example.in');
```

**Warning**: Cannot delete if the host is still referenced by any domain!

---

### 4. Get Glue Record Info

**Method**: `EppClient::infoGlueRecord()`

**Example**:
```php
$info = $client->infoGlueRecord('ns1.example.in');

print_r($info);
/*
Array
(
    [name] => ns1.example.in
    [status] => Array
        (
            [0] => Array
                (
                    [status] => ok
                    [language] => en
                    [message] => 
                )
        )
    [ipAddresses] => Array
        (
            [0] => Array
                (
                    [ip] => 192.0.2.1
                    [version] => v4
                )
            [1] => Array
                (
                    [ip] => 2001:db8::1
                    [version] => v6
                )
        )
    [clID] => NIXI-REGISTRAR
    [crID] => ADMIN123
    [crDate] => 2026-04-17T10:00:00Z
    ...
)
*/
```

---

## 📊 EPP XML Examples

### DNSSEC Enable (DS Record)

**XML Sent**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
    <command>
        <update>
            <domain:update xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
                <domain:name>example.in</domain:name>
            </domain:update>
        </update>
        <extension>
            <secDNS:update xmlns:secDNS="urn:ietf:params:xml:ns:secDNS-1.1">
                <secDNS:add>
                    <secDNS:dsData>
                        <secDNS:keyTag>12345</secDNS:keyTag>
                        <secDNS:alg>13</secDNS:alg>
                        <secDNS:digestType>2</secDNS:digestType>
                        <secDNS:digest>ABC123DEF456...</secDNS:digest>
                    </secDNS:dsData>
                </secDNS:add>
            </secDNS:update>
        </extension>
        <clTRID>NIXI-5f8a9b7c.12345678</clTRID>
    </command>
</epp>
```

**Response**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
    <response>
        <result code="1000">
            <msg>Command completed successfully</msg>
        </result>
        <trID>
            <clTRID>NIXI-5f8a9b7c.12345678</clTRID>
            <svTRID>REGISTRY-ABC123</svTRID>
        </trID>
    </response>
</epp>
```

---

### Glue Record Create

**XML Sent**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
    <command>
        <create>
            <host:create xmlns:host="urn:ietf:params:xml:ns:host-1.0">
                <host:name>ns1.example.in</host:name>
                <host:addr ip="v4">192.0.2.1</host:addr>
                <host:addr ip="v6">2001:db8::1</host:addr>
            </host:create>
        </create>
        <clTRID>NIXI-5f8a9b7c.12345678</clTRID>
    </command>
</epp>
```

---

## 🎯 Complete Workflow Examples

### Example 1: Register Domain with DNSSEC

```php
<?php
require_once 'EppClient.php';

$client = new EppClient(...);
$client->connect();
$client->login('registrar', 'password');

// 1. Create contact
$contactId = $client->createContact([...]);

// 2. Create domain with DNSSEC
$domainData = [
    'auth_code' => 'TransferAuth123',
    'dnssec' => [
        'dsData' => [
            [
                'keyTag' => 23706,
                'algorithm' => 13,  // ECDSAP256SHA256
                'digestType' => 2,  // SHA-256
                'digest' => '4A8549660A6F5C7F4D8B...',
            ]
        ]
    ]
];

$response = $client->createDomain(
    'example.in',
    $contactId,
    ['ns1.example.in', 'ns2.example.in'],
    1,
    $domainData
);

if ($response->isSuccess()) {
    echo "Domain created with DNSSEC!\n";
}

$client->logout();
$client->disconnect();
```

---

### Example 2: Setup Glue Records and Use Them

```php
<?php
// 1. Create glue records
$client->createGlueRecord('ns1.example.in', ['192.0.2.1']);
$client->createGlueRecord('ns2.example.in', ['192.0.2.2']);

// 2. Verify glue records
$ns1Info = $client->infoGlueRecord('ns1.example.in');
echo "ns1.example.in IP: " . $ns1Info['ipAddresses'][0]['ip'] . "\n";

// 3. Use glue records for domain
$client->createDomain(
    'example.in',
    $contactId,
    ['ns1.example.in', 'ns2.example.in'],
    1
);
```

---

### Example 3: Rotate DNSSEC Keys

```php
<?php
// Scenario: Rotating from old key to new key

// 1. Add new DS record (keep old one)
$client->updateDNSSEC('example.in', [
    // New key
    [
        'keyTag' => 12346,
        'algorithm' => 15,  // ED25519
        'digestType' => 2,
        'digest' => 'NEWKEY789...',
    ]
]);

// Wait for propagation (24-48 hours)

// 2. Remove old DS record
$client->updateDNSSEC('example.in', [], [
    // Old key
    [
        'keyTag' => 12345,
        'algorithm' => 13,
        'digestType' => 2,
        'digest' => 'OLDKEY123...',
    ]
]);

echo "DNSSEC key rotation complete!\n";
```

---

### Example 4: Complete Domain Setup with DNSSEC and Glue

```php
<?php
// Full workflow for new domain with custom nameservers and DNSSEC

$client->connect();
$client->login('registrar', 'password');

// Step 1: Create glue records
$client->createGlueRecord('ns1.example.in', ['192.0.2.1', '2001:db8::1']);
$client->createGlueRecord('ns2.example.in', ['192.0.2.2', '2001:db8::2']);

// Step 2: Create contact
$contactId = $client->createContact([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1.1234567890',
    // ... other fields
]);

// Step 3: Create domain with DNSSEC
$domainData = [
    'auth_code' => 'SecureAuth123',
    'dnssec' => [
        'dsData' => [
            [
                'keyTag' => 23706,
                'algorithm' => 13,
                'digestType' => 2,
                'digest' => '4A8549660A6F5C7F4D8B...',
            ]
        ]
    ]
];

$client->createDomain(
    'example.in',
    $contactId,
    ['ns1.example.in', 'ns2.example.in'],
    1,
    $domainData
);

echo "Domain setup complete with DNSSEC and glue records!\n";

$client->logout();
$client->disconnect();
```

---

## 📋 Service.php Integration (FOSSBilling)

### Add these methods to Service.php:

```php
/**
 * Enable DNSSEC for domain
 */
public function enableDNSSEC(\Model_Tld $tld, array $domainData): bool
{
    try {
        $client = $this->getEppClient();
        $client->connect();
        $client->login();

        $domainName = $domainData['sld'] . '.' . $tld->tld;
        $dsData = $domainData['ds_data'] ?? [];

        $result = $client->enableDNSSEC($domainName, $dsData);

        $client->logout();
        $client->disconnect();

        if (!$result->isSuccess()) {
            throw new \Exception('DNSSEC enable failed: ' . $result->getMessage());
        }

        $this->getLog()->info('DNSSEC enabled for: ' . $domainName);
        return true;
    } catch (\Exception $e) {
        $this->getLog()->err('Failed to enable DNSSEC: ' . $e->getMessage());
        throw new \Exception('Failed to enable DNSSEC: ' . $e->getMessage());
    }
}

/**
 * Disable DNSSEC for domain
 */
public function disableDNSSEC(\Model_Tld $tld, array $domainData): bool
{
    try {
        $client = $this->getEppClient();
        $client->connect();
        $client->login();

        $domainName = $domainData['sld'] . '.' . $tld->tld;
        $result = $client->disableDNSSEC($domainName);

        $client->logout();
        $client->disconnect();

        if (!$result->isSuccess()) {
            throw new \Exception('DNSSEC disable failed: ' . $result->getMessage());
        }

        $this->getLog()->info('DNSSEC disabled for: ' . $domainName);
        return true;
    } catch (\Exception $e) {
        $this->getLog()->err('Failed to disable DNSSEC: ' . $e->getMessage());
        throw new \Exception('Failed to disable DNSSEC: ' . $e->getMessage());
    }
}

/**
 * Create glue record
 */
public function createGlueRecord(string $hostName, array $ipAddresses = []): bool
{
    try {
        $client = $this->getEppClient();
        $client->connect();
        $client->login();

        $result = $client->createGlueRecord($hostName, $ipAddresses);

        $client->logout();
        $client->disconnect();

        if (!$result->isSuccess()) {
            throw new \Exception('Glue record creation failed: ' . $result->getMessage());
        }

        $this->getLog()->info('Glue record created: ' . $hostName);
        return true;
    } catch (\Exception $e) {
        $this->getLog()->err('Failed to create glue record: ' . $e->getMessage());
        throw new \Exception('Failed to create glue record: ' . $e->getMessage());
    }
}

/**
 * Get glue record info
 */
public function getGlueRecordInfo(string $hostName): array
{
    try {
        $client = $this->getEppClient();
        $client->connect();
        $client->login();

        $info = $client->infoGlueRecord($hostName);

        $client->logout();
        $client->disconnect();

        return $info;
    } catch (\Exception $e) {
        $this->getLog()->err('Failed to get glue record info: ' . $e->getMessage());
        throw new \Exception('Failed to get glue record info: ' . $e->getMessage());
    }
}
```

---

## 🔍 DNSSEC Validation

### Online Tools

1. **DNSViz**: https://dnsviz.net/
2. **Verisign DNSSEC Analyzer**: https://dnssec-debugger.verisignlabs.com/
3. **RIPE DNSSEC Check**: https://dnssec-checker.ripe.net/

### Command Line

```bash
# Check DS record at registry
dig @a.in.afilias-nst.in example.in DS

# Check DNSKEY at nameserver
dig @ns1.example.in example.in DNSKEY

# Validate chain
dig +dnssec example.in A
```

---

## ⚠️ Common Issues

### Issue: "Invalid DS record"

**Causes**:
- Incorrect keyTag
- Invalid algorithm number
- Wrong digest format
- Digest doesn't match DNSKEY

**Solution**:
- Verify DS data matches your DNSKEY
- Use DNSSEC calculator tools
- Check algorithm and digest type numbers

---

### Issue: "Glue record already exists"

**Cause**: Host already created

**Solution**:
```php
// Check if exists first
try {
    $info = $client->infoGlueRecord('ns1.example.in');
    echo "Already exists with IPs: " . implode(', ', array_column($info['ipAddresses'], 'ip'));
} catch (Exception $e) {
    // Doesn't exist, create it
    $client->createGlueRecord('ns1.example.in', ['192.0.2.1']);
}
```

---

### Issue: "Cannot delete glue record"

**Cause**: Host still referenced by domain

**Solution**:
```php
// 1. Remove nameserver from domain first
$client->updateDomainNs('example.in', ['ns1.other-ns.com']);

// 2. Now delete glue record
$client->deleteGlueRecord('ns1.example.in');
```

---

## 📚 RFC References

- **RFC 5730**: EPP Base
- **RFC 5731**: Domain Mapping
- **RFC 5732**: Host Mapping (Glue Records)
- **RFC 4310**: DNSSEC Extension for EPP
- **RFC 5910**: DNSSEC Extension Updates (secDNS-1.1)

---

## ✅ Checklist

### DNSSEC Setup

- [ ] Generate DNSKEY pair
- [ ] Create DS record from DNSKEY
- [ ] Submit DS record via EPP
- [ ] Verify DS at registry
- [ ] Test DNSSEC validation

### Glue Records Setup

- [ ] Identify in-bailiwick nameservers
- [ ] Create glue records with IPs
- [ ] Verify glue records at registry
- [ ] Use glue records for domain
- [ ] Test resolution

---

## 🎉 Summary

Your NixiEpp module now supports:

✅ **DNSSEC**: Complete lifecycle management  
✅ **Glue Records**: Full CRUD operations  
✅ **IPv4 & IPv6**: Dual-stack support  
✅ **Multiple DS Records**: Key rotation support  
✅ **RFC Compliant**: secDNS-1.1 and RFC 5732  
✅ **Production Ready**: Error handling and logging  

**Ready to secure your domains with DNSSEC!** 🔐
