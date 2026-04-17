# Transfer Monitoring System - NixiEpp v1.1.0

## 📋 Overview

Automated transfer monitoring system that:
- ✅ **Monitors incoming transfers** daily until completion
- ✅ **Provides manual status check button** in admin panel
- ✅ **Auto-cleans transferred-out domains** after 7 days
- ✅ **Handles transfer completion, failures, and cancellations**

---

## 🎯 How It Works

### Transfer In Flow

```
Customer Requests Transfer
    ↓
Transfer Initiated (EPP)
    ↓
Status: 'pending' (Database)
    ↓
Daily Cron Job Checks Status
    ↓
[Day 1-5] Still Pending → Check again tomorrow
    ↓
[Day 5] Transfer Complete → Update DB → Notify Customer
```

### Transfer Out Flow

```
Transfer Out Detected
    ↓
Status: 'transferring_out' (Database)
    ↓
Daily Check (Days 1-6) → Still transferring out
    ↓
[Day 7] Delete from Database → Clean up complete
```

---

## 🗄️ Database Schema

### Migration File

**File**: `database_migration_transfer_monitoring.sql`

```sql
CREATE TABLE IF NOT EXISTS `service_domain_transfer` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `domain_name` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'checking', 'completed', 'failed', 'transferring_out', 'transferred_out', 'cancelled'),
    `transfer_direction` ENUM('in', 'out'),
    `transfer_status` VARCHAR(50),
    `transfer_initiated_at` DATETIME,
    `last_checked_at` DATETIME,
    `completed_at` DATETIME,
    `failed_at` DATETIME,
    `transferred_out_at` DATETIME,
    `check_count` INT DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_domain_name` (`domain_name`)
);
```

### Installation

```bash
# Run migration
mysql -u root -p fossbilling < database_migration_transfer_monitoring.sql
```

---

## 🔧 Setup

### Step 1: Run Database Migration

```bash
cd /path/to/fossbilling/src/modules/Servicedomain/Registrar/NixiEpp
mysql -u username -p database_name < database_migration_transfer_monitoring.sql
```

### Step 2: Setup Daily Cron Job

```bash
# Edit crontab
crontab -e

# Add this line (runs at 2 AM daily)
0 2 * * * /usr/bin/php /path/to/fossbilling/src/modules/Servicedomain/Registrar/NixiEpp/transfer_monitor.php >> /var/log/nixiepp-transfers.log 2>&1
```

### Step 3: Test Cron Job

```bash
# Manual test
php transfer_monitor.php --verbose

# Dry run (no DB changes)
php transfer_monitor.php --dry-run --verbose
```

---

## 📊 API Methods

### 1. Transfer Domain (Enhanced)

**Method**: `Service::transferDomain()`

**What Changed**:
- Now automatically marks transfer as 'pending' in database
- Logs transfer initiation with timestamp
- Ready for monitoring

**Example**:
```php
$result = $service->transferDomain($tld, [
    'sld' => 'example',
    'tld' => '.in',
    'auth_code' => 'abc123',
]);
// Transfer is now tracked automatically
```

---

### 2. Check Transfer Status (NEW)

**Method**: `Service::checkTransferStatus()`

**Purpose**: Manual status check (for admin button)

**Returns**:
```php
[
    'success' => true,
    'domain' => 'example.in',
    'status' => ['ok'],
    'transfer_status' => 'pending',
    'expiry_date' => '2027-04-17',
    'registrar' => 'NIXI-REGISTRAR',
    'message' => 'Transfer status retrieved successfully',
]
```

**Usage in FOSSBilling Admin**:
```php
// AJAX endpoint for manual check button
$status = $service->checkTransferStatus($tld, [
    'sld' => $domain['sld'],
    'tld' => '.' . $domain['tld'],
]);

echo json_encode($status);
```

---

### 3. Check All Pending Transfers (NEW)

**Method**: `Service::checkPendingTransfers()`

**Purpose**: Daily automated monitoring

**Returns**:
```php
[
    'total' => 25,
    'completed' => 3,
    'failed' => 1,
    'pending' => 20,
    'transferred_out' => 1,
    'details' => [
        ['domain' => 'example.in', 'action' => 'still_pending', 'days' => 2],
        ['domain' => 'test.in', 'action' => 'transfer_in_completed'],
        ['domain' => 'old.in', 'action' => 'transfer_out_detected'],
    ]
]
```

---

## 🔄 Transfer Statuses

### Status Flow

```
pending
  ↓
checking (temporary, during check)
  ↓
├─ completed (transfer in successful)
├─ failed (rejected/cancelled)
├─ transferring_out (domain moving to another registrar)
└─ transferred_out (deleted from DB after 7 days)
```

### Status Descriptions

| Status | Description | Action |
|--------|-------------|--------|
| `pending` | Transfer initiated, waiting | Monitor daily |
| `checking` | Currently checking status | Temporary |
| `completed` | Transfer finished successfully | Notify customer |
| `failed` | Transfer rejected/cancelled | Notify customer |
| `transferring_out` | Domain leaving our registrar | Monitor for 7 days |
| `transferred_out` | Domain left registrar | Delete from DB |
| `cancelled` | Transfer cancelled manually | Clean up |

---

## 🎨 Admin Panel Integration

### Manual Status Check Button

**Add to FOSSBilling admin template**:

```twig
{# In domain management template #}
{% if domain.registrar == 'NixiEpp' and domain.status == 'pending_transfer' %}
    <button type="button" 
            class="btn btn-primary" 
            onclick="checkTransferStatus('{{ domain.name }}')">
        <i class="fas fa-sync-alt"></i> Check Transfer Status
    </button>
    
    <div id="transfer-status-{{ domain.id }}" class="mt-2"></div>
{% endif %}

<script>
function checkTransferStatus(domainName) {
    const parts = domainName.split('.');
    const sld = parts[0];
    const tld = '.' + parts.slice(1).join('.');
    
    fetch('/admin/api/domain/check_transfer_status', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({sld: sld, tld: tld})
    })
    .then(response => response.json())
    .then(data => {
        const statusDiv = document.getElementById('transfer-status-' + domain.id);
        
        if (data.success) {
            let statusColor = 'warning';
            if (data.transfer_status.includes('approved')) {
                statusColor = 'success';
            } else if (data.transfer_status.includes('rejected')) {
                statusColor = 'danger';
            }
            
            statusDiv.innerHTML = `
                <div class="alert alert-${statusColor}">
                    <strong>Transfer Status:</strong> ${data.transfer_status}<br>
                    <strong>Domain Status:</strong> ${data.status.join(', ')}<br>
                    <strong>Expiry:</strong> ${data.expiry_date || 'N/A'}
                </div>
            `;
        } else {
            statusDiv.innerHTML = `
                <div class="alert alert-danger">
                    ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>
```

---

## 📅 Cron Job Details

### Daily Transfer Monitor

**File**: `transfer_monitor.php`

**What It Does**:
1. Gets all domains using NixiEpp registrar
2. Checks each domain's transfer status via EPP
3. Updates database with current status
4. Handles completed/failed/transferred-out transfers
5. Logs all actions

**Schedule**: Daily at 2:00 AM

**Log Output**:
```
========================================
  NixiEpp Transfer Monitor
  2026-04-17 02:00:00
========================================

Found 25 domains to check

Checking: example.in
  Status: "pending"
Checking: test.in
  Status: "client_approved"

========================================
  Summary
========================================

Total Domains: 25
Pending Transfers: 20
Completed: 3
Failed: 1
Transferred Out: 1
Errors: 0

Monitoring completed at 2026-04-17 02:01:30
```

---

## 🔍 Transfer Monitoring Logic

### Automatic Checks (Daily Cron)

```php
foreach ($pendingTransfers as $transfer) {
    $domainName = $transfer['domain_name'];
    $daysSinceInitiated = $transfer['days_since_initiated'];
    
    // Check via EPP
    $status = $this->getTransferStatus($domainName);
    
    // Handle based on status
    if ($status['transfer_status'] === 'client_approved') {
        // Transfer IN completed
        $this->completeTransferIn($domainName, $status);
        
    } elseif ($status['is_transferred_out'] === true) {
        // Transfer OUT detected
        if ($daysSinceInitiated >= 7) {
            // Delete from DB after 7 days
            $this->deleteTransferredOutDomain($domainName);
        }
    }
}
```

### Manual Check (Admin Button)

```php
// User clicks "Check Transfer Status" button
public function checkTransferStatus($tld, $domainData) {
    // Immediate EPP check
    $domainInfo = $client->infoDomain($domainName);
    
    // Return status to UI
    return [
        'success' => true,
        'transfer_status' => $domainInfo['transfer_status'],
        'status' => $domainInfo['status'],
    ];
}
```

---

## 🎯 Key Features

### 1. Daily Automatic Monitoring

- ✅ Checks all pending transfers every day
- ✅ No manual intervention needed
- ✅ Automatic completion detection
- ✅ Error handling and logging

### 2. Manual Status Check Button

- ✅ Instant status check from admin panel
- ✅ Real-time EPP query
- ✅ Detailed status display
- ✅ Error messages if check fails

### 3. Automatic Cleanup (Transfer Out)

- ✅ Detects when domain transfers out
- ✅ Waits 7 days (grace period)
- ✅ Automatically deletes from database
- ✅ Preserves transfer history

### 4. Comprehensive Logging

- ✅ All transfers logged
- ✅ Status changes tracked
- ✅ Check count recorded
- ✅ Failure reasons stored

---

## 📊 Database Queries

### Get Pending Transfers

```sql
SELECT * FROM service_domain_transfer 
WHERE status IN ('pending', 'checking')
ORDER BY transfer_initiated_at ASC;
```

### Update Transfer Status

```sql
UPDATE service_domain_transfer 
SET status = 'completed',
    completed_at = NOW(),
    transfer_status = 'client_approved'
WHERE domain_name = 'example.in';
```

### Delete Transferred Out Domain

```sql
-- After 7 days
DELETE FROM service_domain 
WHERE name = 'transferred-out.in';

-- Update transfer record
UPDATE service_domain_transfer 
SET status = 'transferred_out',
    transferred_out_at = NOW()
WHERE domain_name = 'transferred-out.in';
```

---

## 🐛 Troubleshooting

### Issue: Cron Job Not Running

**Check**:
```bash
# Verify crontab
crontab -l

# Check if cron daemon is running
systemctl status cron

# Test manually
php transfer_monitor.php --verbose
```

---

### Issue: Transfer Not Completing

**Possible Causes**:
- Losing registrar not responding
- Auth code invalid
- Domain locked

**Solution**:
```bash
# Check transfer status manually
php -r "
require 'transfer_monitor.php';
\$status = \$service->checkTransferStatus(\$tld, ['sld' => 'example', 'tld' => '.in']);
print_r(\$status);
"

# Check logs
tail -f /var/log/nixiepp-transfers.log
```

---

### Issue: Database Errors

**Check Table Exists**:
```sql
SHOW TABLES LIKE 'service_domain_transfer';
```

**Check Table Structure**:
```sql
DESCRIBE service_domain_transfer;
```

**Re-run Migration**:
```bash
mysql -u root -p fossbilling < database_migration_transfer_monitoring.sql
```

---

## 📈 Monitoring Dashboard (Optional)

### Admin Dashboard Widget

```php
// Get transfer statistics
$sql = "SELECT 
            status,
            COUNT(*) as count
        FROM service_domain_transfer
        WHERE DATE(transfer_initiated_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY status";

$stats = $di['pdo']->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($stats);
```

### Display in Admin Panel

```twig
<div class="card">
    <h3>Transfer Statistics (Last 30 Days)</h3>
    <ul>
        <li>Pending: {{ stats.pending|default(0) }}</li>
        <li>Completed: {{ stats.completed|default(0) }}</li>
        <li>Failed: {{ stats.failed|default(0) }}</li>
        <li>Transferred Out: {{ stats.transferred_out|default(0) }}</li>
    </ul>
</div>
```

---

## ✅ Testing Checklist

### Test Transfer In

- [ ] Initiate transfer via FOSSBilling
- [ ] Verify transfer marked as 'pending' in DB
- [ ] Run cron job manually
- [ ] Verify status updates correctly
- [ ] Confirm completion notification

### Test Transfer Out

- [ ] Initiate transfer out
- [ ] Verify status = 'transferring_out'
- [ ] Wait 7 days (or modify for testing)
- [ ] Verify domain deleted from DB
- [ ] Check transfer record preserved

### Test Manual Check Button

- [ ] Click "Check Transfer Status" button
- [ ] Verify instant response
- [ ] Check status displayed correctly
- [ ] Test with locked domains
- [ ] Test error handling

---

## 🚀 Migration from v1.0.0

### Step 1: Backup Database

```bash
mysqldump -u root -p fossbilling > backup_before_v1.1.sql
```

### Step 2: Run Migration

```bash
mysql -u root -p fossbilling < database_migration_transfer_monitoring.sql
```

### Step 3: Update Module Files

Replace existing files with new versions:
- `Service.php` (enhanced with transfer monitoring)
- Add `transfer_monitor.php` (new cron job)
- Add `database_migration_transfer_monitoring.sql` (new)

### Step 4: Setup Cron Job

```bash
crontab -e
# Add: 0 2 * * * /usr/bin/php /path/to/transfer_monitor.php
```

### Step 5: Test

```bash
php transfer_monitor.php --dry-run --verbose
```

---

## 📚 Related Documentation

- [Main README](README.md)
- [API Reference](API_REFERENCE.md)
- [Transfer Testing Guide](TRANSFER_TESTING.md)
- [Lifecycle Guide](LIFECYCLE.md)
- [Deployment Guide](DEPLOYMENT.md)

---

## 🎉 Summary

The transfer monitoring system provides:

1. **Automated Daily Checks** - No manual tracking needed
2. **Manual Status Button** - Instant status checks from admin
3. **Automatic Cleanup** - Transferred-out domains removed after 7 days
4. **Comprehensive Logging** - Full audit trail
5. **Error Handling** - Graceful failure management

**Ready to monitor all your domain transfers automatically!** 🚀
