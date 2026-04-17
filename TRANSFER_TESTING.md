# Transfer Testing Guide

Complete guide for testing domain transfer functionality in NixiEpp module.

---

## 📋 Overview

This guide covers testing all aspects of domain transfers:

✅ Transfer initiation  
✅ Transfer code (auth code) retrieval  
✅ Transfer status checking  
✅ Transfer locking/unlocking  
✅ Error handling  
✅ Edge cases  

---

## 🚀 Quick Start

### 1. Configure Test Script

Edit `test_transfers.php`:

```php
// Update these values
$config = [
    'epp_host' => 'epp.registry.com',      // Your EPP server
    'epp_port' => 700,                      // EPP port
    'username' => 'your_registrar_id',      // EPP username
    'password' => 'your_password',          // EPP password
    'ssl_cert' => '/path/to/client-cert.pem',
    'ssl_key' => '/path/to/client-key.pem',
    'ssl_ca' => '/path/to/ca-bundle.crt',
];

// Add test domains
$testDomains = [
    [
        'domain' => 'example.in',
        'auth_code' => 'actual-auth-code',
    ],
];
```

### 2. Run Tests

```bash
# Dry run (no actual EPP commands)
php test_transfers.php --dry-run --verbose

# Live test (sends actual EPP commands)
php test_transfers.php --verbose
```

---

## 🧪 Test Scenarios

### Test 1: Basic Transfer Initiation

**Purpose**: Verify domain transfer can be initiated

**Steps**:
1. Get auth code from current registrar
2. Run transfer test
3. Verify transfer is pending

**Expected Result**:
```
Result Code: 1001
Message: Command completed successfully; action pending
✓ Transfer initiated successfully
ℹ Transfer is pending (normal for most registries)
```

**EPP Command**:
```xml
<transfer op="request">
    <domain:transfer>
        <domain:name>example.in</domain:name>
        <domain:period unit="y">1</domain:period>
        <domain:authInfo>
            <domain:pw>auth-code-here</domain:pw>
        </domain:authInfo>
    </domain:transfer>
</transfer>
```

---

### Test 2: Transfer Code Retrieval

**Purpose**: Verify auth code can be retrieved for owned domains

**Steps**:
1. Use a domain you already own
2. Request transfer code
3. Verify code is returned

**Expected Result**:
```
✓ Transfer code retrieved
Auth Code: abc*** (hidden for security)
```

**Note**: Some registries don't support auth code retrieval via EPP.

---

### Test 3: Transfer with Invalid Auth Code

**Purpose**: Verify error handling for invalid credentials

**Test Data**:
```php
$testDomains = [
    [
        'domain' => 'example.in',
        'auth_code' => 'invalid-code',
    ],
];
```

**Expected Result**:
```
Result Code: 2202
Message: Authorization error
✗ Transfer failed
```

**Common Error Codes**:
- `2202` - Invalid auth code
- `2303` - Domain not found
- `2201` - Authorization failed

---

### Test 4: Transfer Locked Domain

**Purpose**: Verify transfer fails for locked domains

**Prerequisites**:
- Domain must have `clientTransferProhibited` status

**Expected Result**:
```
Result Code: 2200
Message: Transfer prohibited
✗ Transfer failed
```

**Solution**: Unlock domain first using:
```php
$client->unlockDomain('example.in');
```

---

### Test 5: Transfer Within 60 Days

**Purpose**: Verify 60-day transfer lock is enforced

**Context**: ICANN policy prohibits transfers within 60 days of:
- New registration
- Previous transfer
- Registrant contact change

**Expected Result**:
```
Result Code: 2200
Message: Transfer not allowed within 60 days
✗ Transfer failed
```

---

### Test 6: Transfer Pending Approval

**Purpose**: Verify pending transfer status handling

**After initiating transfer**:
1. Check domain info
2. Verify transfer status is pending
3. Check for pending transfer ID

**Expected Domain Status**:
```
status: pendingTransfer
pending transfer: {transfer-id}
```

---

### Test 7: Transfer Cancellation

**Purpose**: Verify transfer can be cancelled

**Registry Commands**:
```xml
<!-- Reject transfer (losing registrar) -->
<transfer op="reject">
    <domain:name>example.in</domain:name>
</transfer>

<!-- Cancel transfer (gaining registrar) -->
<transfer op="cancel">
    <domain:name>example.in</domain:name>
</transfer>
```

**Note**: Current implementation supports `request` operation only. Reject/cancel operations can be added if needed.

---

## 🔍 Transfer Status Codes

### EPP Result Codes

| Code | Meaning | Action |
|------|---------|--------|
| 1000 | Success | Transfer completed immediately |
| 1001 | Pending | Transfer requires approval (normal) |
| 2200 | Transfer prohibited | Domain is locked |
| 2201 | Authorization error | Invalid credentials |
| 2202 | Invalid auth code | Wrong transfer code |
| 2303 | Object not found | Domain doesn't exist |
| 2304 | Status prohibits | Domain status blocks transfer |

### Domain Status Codes

| Status | Transfer Allowed | Description |
|--------|------------------|-------------|
| `ok` | ✅ Yes | Normal status |
| `clientTransferProhibited` | ❌ No | Locked by registrar |
| `serverTransferProhibited` | ❌ No | Locked by registry |
| `pendingTransfer` | ❌ No | Transfer in progress |
| `clientHold` | ❌ No | Suspended domain |
| `serverHold` | ❌ No | Registry suspended |

---

## 📊 Transfer Flow

### Standard Transfer Process

```
Day 0:  Transfer initiated (auth code validated)
Day 1:  Losing registrar notified
Day 5:  Auto-approve if no response
Day 7:  Transfer completes (if approved)
```

### Transfer States

```
Initiated → Pending → Approved → Completed
                ↓
            Rejected
                ↓
            Cancelled
```

---

## 🛠️ Manual Testing

### Test via FOSSBilling Admin Panel

1. **Navigate**: Settings → Domain Management → Registrars → NixiEpp
2. **Test Connection**: Verify connection works
3. **Create Order**: Create a domain transfer order
4. **Enter Details**:
   - Domain name
   - Auth code
   - Customer information
5. **Process Order**: FOSSBilling will initiate transfer
6. **Monitor**: Check order status and logs

### Test via Direct API

```php
// Bootstrap FOSSBilling
require_once 'load.php';

// Get registrar service
$registrar = new \Box\Mod\Servicedomain\Registrar\NixiEpp\Service($config);

// Initiate transfer
$result = $registrar->transferDomain($tld, [
    'sld' => 'example',
    'tld' => '.in',
    'auth_code' => 'auth-code-here',
    'transfer_period' => 1,
]);

echo $result ? 'Transfer initiated' : 'Transfer failed';
```

---

## 🐛 Troubleshooting

### Issue: "Authorization error" (2201)

**Causes**:
- Invalid EPP credentials
- IP not whitelisted
- Account suspended

**Solutions**:
1. Verify username/password
2. Check IP whitelist with registry
3. Contact registry support

---

### Issue: "Invalid auth code" (2202)

**Causes**:
- Wrong auth code
- Auth code expired
- Domain recently transferred

**Solutions**:
1. Get fresh auth code from current registrar
2. Verify domain is eligible for transfer
3. Check if 60-day lock applies

---

### Issue: "Transfer prohibited" (2200)

**Causes**:
- Domain locked
- Pending transfer exists
- Registry lock enabled

**Solutions**:
```php
// Unlock domain
$client->unlockDomain('example.in');

// Check status
$info = $client->infoDomain('example.in');
print_r($info['statuses']);
```

---

### Issue: Connection Timeout

**Causes**:
- Firewall blocking port 700
- EPP server down
- Network issues

**Solutions**:
```bash
# Test connectivity
telnet epp.registry.com 700

# Test SSL
openssl s_client -connect epp.registry.com:700 \
  -cert client-cert.pem \
  -key client-key.pem \
  -CAfile ca-bundle.crt
```

---

## 📝 Test Checklist

### Pre-Transfer Tests

- [ ] EPP connection successful
- [ ] Authentication working
- [ ] Domain availability checked
- [ ] Auth code obtained
- [ ] Domain not locked
- [ ] No pending transfers
- [ ] 60-day lock not applicable

### During Transfer Tests

- [ ] Transfer initiated successfully
- [ ] Status shows pending
- [ ] Transfer ID received
- [ ] Logs show transfer attempt
- [ ] Customer notified

### Post-Transfer Tests

- [ ] Transfer completed (or pending approval)
- [ ] Domain appears in account
- [ ] Nameservers preserved or updated
- [ ] Expiry date extended by 1 year
- [ ] WHOIS updated

---

## 🎯 NIXI-Specific Notes

### .IN Registry Transfer Rules

1. **Transfer Period**: Usually 5-7 days
2. **Auto-Renewal**: Transfer includes 1-year renewal
3. **Auth Code**: Required and validated
4. **60-Day Lock**: Enforced per ICANN policy
5. **Status Check**: Use `clientTransferProhibited` for locking

### Transfer Pricing

- Transfer includes 1-year renewal
- Registry charges renewal fee
- No separate transfer fee (usually)

---

## 📚 Additional Resources

- **EPP RFC 5731**: https://tools.ietf.org/html/rfc5731 (Domain Mapping)
- **NIXI Policies**: https://registry.in/policies
- **ICANN Transfer Policy**: https://www.icann.org/transfers
- **Test Script**: `test_transfers.php`

---

## 🔐 Security Notes

1. **Never commit auth codes** to repository
2. **Use test domains** for testing
3. **Rotate EPP passwords** regularly
4. **Monitor logs** for failed attempts
5. **Use --dry-run** for safe testing

---

## ✅ Testing Complete

After running all tests, verify:

- ✅ All expected results match
- ✅ Error handling works correctly
- ✅ Logs capture all operations
- ✅ No unexpected side effects
- ✅ Transfer flow is complete

**Ready for production!** 🎉
