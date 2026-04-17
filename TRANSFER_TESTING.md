# Transfer Testing Guide - NixiEpp

## 📋 Overview

This guide covers testing domain transfer functionality in the NixiEpp module, including:

- Transfer initiation
- Transfer status monitoring
- Transfer code (auth code) retrieval
- Error handling
- Edge cases

---

## 🎯 Transfer Flow

### Standard Transfer Process

```
Customer Request
    ↓
FOSSBilling UI
    ↓
Service.transferDomain()
    ↓
EppClient.transferDomain()
    ↓
EppFrame.createDomainTransfer()
    ↓
EPP Server (Registry)
    ↓
Response (Success/Pending/Error)
    ↓
FOSSBilling Database Update
```

### Transfer Timeline

```
Day 0:   Transfer initiated
Day 1-5: Pending (waiting for losing registrar approval)
Day 5:   Auto-approved if no response
Day 5+:  Transfer complete, domain active
```

---

## 🧪 Testing Methods

### Method 1: Automated Test Script

**File**: `test_transfers.php`

#### Quick Start

```bash
# 1. Edit configuration
vim test_transfers.php

# Update these values:
$config = [
    'epp_host' => 'epp.registry.com',
    'epp_port' => 700,
    'username' => 'your_registrar_id',
    'password' => 'your_password',
    'ssl_cert' => '/path/to/client-cert.pem',
    'ssl_key' => '/path/to/client-key.pem',
    'ssl_ca' => '/path/to/ca-bundle.crt',
];

# 2. Add test domains
$testDomains = [
    [
        'domain' => 'example.in',
        'auth_code' => 'test-auth-code-123',
    ],
];

# 3. Run in dry-run mode (safe)
php test_transfers.php --dry-run --verbose

# 4. Run actual tests (when ready)
php test_transfers.php --verbose
```

#### Test Script Features

✅ **Connection Testing** - Verifies EPP connectivity  
✅ **Transfer Initiation** - Tests domain transfer requests  
✅ **Auth Code Retrieval** - Tests transfer code fetching  
✅ **Error Handling** - Validates error responses  
✅ **Verbose Mode** - Shows full responses  
✅ **Dry Run Mode** - Safe testing without actual transfers  

---

### Method 2: Manual Testing via FOSSBilling

#### Step 1: Configure Module

1. Log in to FOSSBilling Admin
2. Navigate to **Settings → Domain Management → Registrars**
3. Find **NixiEpp Registrar**
4. Click **Configure**
5. Verify EPP credentials
6. Click **Test Connection**

#### Step 2: Initiate Transfer

1. Go to **Orders → New Order**
2. Select **Domain Transfer**
3. Enter domain name (e.g., `example.in`)
4. Enter auth code from current registrar
5. Select TLD
6. Complete order

#### Step 3: Monitor Transfer

1. Navigate to **Domains → Manage Domains**
2. Find transferred domain
3. Check status:
   - **Pending** - Transfer in progress
   - **Active** - Transfer complete
   - **Failed** - Transfer failed

---

### Method 3: Direct EPP Testing

#### Test Transfer Command

```php
<?php
require_once 'EppClient.php';

use Box\Mod\Servicedomain\Registrar\NixiEpp\EppClient;

$client = new EppClient(
    'epp.registry.com',
    700,
    '/path/to/client-cert.pem',
    '/path/to/client-key.pem',
    '/path/to/ca-bundle.crt'
);

$client->connect();
$client->login('registrar_id', 'password');

// Test transfer
$response = $client->transferDomain('example.in', 'auth-code-123');

echo "Result Code: " . $response->getResultCode() . "\n";
echo "Message: " . $response->getMessage() . "\n";
echo "Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";

$client->logout();
$client->disconnect();
```

---

## 📊 EPP Transfer Response Codes

### Success Codes

| Code | Meaning | Action |
|------|---------|--------|
| **1000** | Transfer complete | Domain transferred successfully |
| **1001** | Transfer pending | Waiting for approval (normal) |

### Error Codes

| Code | Meaning | Solution |
|------|---------|----------|
| **2001** | Syntax error | Check XML format |
| **2005** | Parameter error | Verify domain name and auth code |
| **2201** | Authorization failed | Check EPP credentials |
| **2303** | Domain not found | Verify domain exists |
| **2304** | Status prohibits transfer | Remove clientTransferProhibited |
| **2306** | Auth code invalid | Verify with losing registrar |
| **2400** | Command failed | Check server logs |

---

## 🔍 Transfer Status Checking

### Check Transfer Status via EPP

```php
<?php
// Get domain info to check transfer status
$domainInfo = $client->infoDomain('example.in');

echo "Status: " . implode(', ', $domainInfo['status']) . "\n";
echo "Transfer Date: " . $domainInfo['transfer_date'] . "\n";
echo "Expiry Date: " . $domainInfo['expiry_date'] . "\n";
```

### Common Domain Statuses

| Status | Meaning | Transfer Impact |
|--------|---------|-----------------|
| `ok` | Normal | ✅ Transfer allowed |
| `clientTransferProhibited` | Locked by registrar | ❌ Transfer blocked |
| `serverTransferProhibited` | Locked by registry | ❌ Transfer blocked |
| `pendingTransfer` | Transfer in progress | ⏳ Wait for completion |
| `clientHold` | Suspended | ❌ Transfer blocked |

---

## 🧪 Test Scenarios

### Scenario 1: Successful Transfer

**Setup**:
- Domain: `test-example.in`
- Auth Code: Valid code from current registrar
- Status: Unlocked (no clientTransferProhibited)

**Expected Result**:
```
Result Code: 1001
Message: Command completed successfully; action pending
Status: pendingTransfer
```

**Verification**:
```bash
# Check domain info
php -r "
require 'EppClient.php';
\$client = new EppClient(...);
\$client->connect();
\$client->login(...);
\$info = \$client->infoDomain('test-example.in');
print_r(\$info);
"
```

---

### Scenario 2: Invalid Auth Code

**Setup**:
- Domain: `example.in`
- Auth Code: Wrong code

**Expected Result**:
```
Result Code: 2306
Message: Authorization error; invalid auth code
```

**Test**:
```php
try {
    $response = $client->transferDomain('example.in', 'wrong-code');
    if (!$response->isSuccess()) {
        echo "Expected error: " . $response->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
```

---

### Scenario 3: Locked Domain

**Setup**:
- Domain: `locked-domain.in`
- Status: `clientTransferProhibited`

**Expected Result**:
```
Result Code: 2304
Message: Object status prohibits operation
```

**Solution**:
```php
// Unlock domain first
$client->unlockDomain('locked-domain.in');

// Then transfer
$client->transferDomain('locked-domain.in', 'auth-code');
```

---

### Scenario 4: Transfer Code Retrieval

**Test**:
```php
try {
    $authCode = $client->getTransferCode('example.in');
    echo "Auth Code: " . $authCode . "\n";
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
```

**Expected**:
- ✅ Success: Auth code returned
- ⚠️ Warning: Registry doesn't support retrieval
- ❌ Error: Domain not found or unauthorized

---

## 📝 Transfer XML Examples

### Transfer Request (What We Send)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
    <command>
        <transfer op="request">
            <domain:transfer xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
                <domain:name>example.in</domain:name>
                <domain:period unit="y">1</domain:period>
                <domain:authInfo>
                    <domain:pw>auth-code-123</domain:pw>
                </domain:authInfo>
            </domain:transfer>
        </transfer>
        <clTRID>NIXI-5f8a9b7c3d2e1.12345678</clTRID>
    </command>
</epp>
```

### Success Response (Pending)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
    <response>
        <result code="1001">
            <msg>Command completed successfully; action pending</msg>
        </result>
        <resData>
            <domain:trnData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
                <domain:name>example.in</domain:name>
                <domain:trStatus>pending</domain:trStatus>
                <domain:reID>NIXI-REGISTRAR</domain:reID>
                <domain:reDate>2026-04-17T10:30:00Z</domain:reDate>
                <domain:acID>LOSING-REGISTRAR</domain:acID>
                <domain:acDate>2026-04-22T10:30:00Z</domain:acDate>
                <domain:exDate>2027-04-17T10:30:00Z</domain:exDate>
            </domain:trnData>
        </resData>
        <trID>
            <clTRID>NIXI-5f8a9b7c3d2e1.12345678</clTRID>
            <svTRID>REGISTRY-ABC123</svTRID>
        </trID>
    </response>
</epp>
```

---

## 🔧 Troubleshooting

### Issue: Transfer Fails with Code 2304

**Problem**: Domain status prohibits transfer

**Solution**:
```php
// 1. Check domain status
$info = $client->infoDomain('example.in');
print_r($info['status']);

// 2. If clientTransferProhibited exists, unlock
if (in_array('clientTransferProhibited', $info['status'])) {
    $client->unlockDomain('example.in');
}

// 3. Retry transfer
$client->transferDomain('example.in', 'auth-code');
```

---

### Issue: Transfer Fails with Code 2306

**Problem**: Invalid authorization code

**Solution**:
1. Verify auth code with losing registrar
2. Check for typos or special characters
3. Request new auth code if expired
4. Test with known working code

---

### Issue: Transfer Stuck in Pending

**Problem**: Transfer status is `pending` for too long

**Possible Causes**:
- Losing registrar hasn't responded
- Registry processing delay
- Missing approval from domain owner

**Solution**:
```php
// Check transfer status
$info = $client->infoDomain('example.in');

// Check pending transfer date
if (isset($info['transfer_date'])) {
    $pendingSince = new DateTime($info['transfer_date']);
    $now = new DateTime();
    $daysPending = $now->diff($pendingSince)->days;
    
    echo "Transfer pending for {$daysPending} days\n";
    
    if ($daysPending > 5) {
        echo "Contact registry support\n";
    }
}
```

---

### Issue: Cannot Retrieve Auth Code

**Problem**: `getTransferCode()` fails or returns empty

**Possible Causes**:
- Registry doesn't support auth code retrieval
- Domain not managed by your registrar
- Permission denied

**Solution**:
```php
try {
    $authCode = $client->getTransferCode('example.in');
    if (empty($authCode)) {
        echo "Registry doesn't support auth code retrieval\n";
        echo "Customer must obtain from current registrar\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Check domain ownership and permissions\n";
}
```

---

## 📈 Transfer Monitoring

### Automated Status Check Script

```php
<?php
/**
 * Monitor pending transfers
 */

require_once 'Service.php';

// Get all pending transfers from database
$pendingTransfers = getPendingTransfers();

foreach ($pendingTransfers as $transfer) {
    $domain = $transfer['domain'];
    
    try {
        // Check status via EPP
        $info = $client->infoDomain($domain);
        
        // Update database
        if ($info['status'] === 'ok') {
            updateTransferStatus($domain, 'completed');
            notifyCustomer($domain, 'Transfer complete!');
        }
        
    } catch (Exception $e) {
        logError("Failed to check {$domain}: " . $e->getMessage());
    }
}
```

### Setup Cron Job

```bash
# Check transfers every 6 hours
0 */6 * * * /usr/bin/php /path/to/check_transfers.php
```

---

## ✅ Transfer Checklist

### Before Transfer

- [ ] Domain is unlocked (no clientTransferProhibited)
- [ ] Auth code obtained from current registrar
- [ ] Domain is not expired or in redemption
- [ ] WHOIS email is accessible (for approval)
- [ ] Domain is older than 60 days (ICANN rule)
- [ ] No disputes or legal issues on domain

### During Transfer

- [ ] Transfer initiated via EPP
- [ ] Response code logged (1001 = pending)
- [ ] Customer notified of pending status
- [ ] Transfer tracked in FOSSBilling

### After Transfer

- [ ] Transfer status updated to "Active"
- [ ] Domain nameservers configured
- [ ] Customer notified of completion
- [ ] Domain locked (clientTransferProhibited added)
- [ ] Auto-renewal enabled
- [ ] WHOIS privacy configured (if requested)

---

## 🎓 Best Practices

### 1. Always Validate Input

```php
// Validate domain name
if (!preg_match('/^[a-z0-9-]+\.[a-z]{2,}$/i', $domainName)) {
    throw new Exception('Invalid domain name format');
}

// Validate auth code
if (empty($authCode) || strlen($authCode) < 6) {
    throw new Exception('Invalid authorization code');
}
```

### 2. Log Everything

```php
$this->getLog()->info('Transfer initiated', [
    'domain' => $domainName,
    'registrar' => 'NixiEpp',
    'timestamp' => date('c'),
]);
```

### 3. Handle Errors Gracefully

```php
try {
    $result = $client->transferDomain($domain, $authCode);
} catch (Exception $e) {
    // Log error
    $this->getLog()->err('Transfer failed: ' . $e->getMessage());
    
    // Notify customer
    notifyCustomer($domain, 'Transfer failed. Please contact support.');
    
    // Don't expose technical details
    throw new Exception('Domain transfer failed. Please verify auth code.');
}
```

### 4. Implement Retry Logic

```php
$maxRetries = 3;
$retryCount = 0;

while ($retryCount < $maxRetries) {
    try {
        $result = $client->transferDomain($domain, $authCode);
        break; // Success
    } catch (Exception $e) {
        $retryCount++;
        if ($retryCount >= $maxRetries) {
            throw $e; // Give up
        }
        sleep(5); // Wait before retry
    }
}
```

---

## 📚 Related Documentation

- [API Reference](API_REFERENCE.md) - Complete API documentation
- [Installation Guide](INSTALL.md) - Setup instructions
- [EPP Commands](IMPLEMENTATION_ANALYSIS.md) - Technical details
- [Lifecycle Guide](LIFECYCLE.md) - Domain lifecycle management

---

## 🆘 Support

**Issues?** Check these resources:

1. **Logs**: `/var/log/fossbilling.log`
2. **EPP Server Logs**: Contact registry
3. **Documentation**: See files above
4. **GitHub Issues**: https://github.com/YOUR_USERNAME/NixiEpp/issues

---

**Ready to test?** Run the automated test script:

```bash
php test_transfers.php --dry-run --verbose
```
