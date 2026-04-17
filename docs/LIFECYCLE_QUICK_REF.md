# NIXI Lifecycle Service - Quick Reference

## 🚀 Quick Start

### 1. Deploy

```bash
# Files already created:
✅ LifecycleService.php
✅ lifecycle_runner.php
✅ LIFECYCLE.md
```

### 2. Create Database Table

```sql
CREATE TABLE domains (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain VARCHAR(255) NOT NULL UNIQUE,
    expiry_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    auto_renewed TINYINT(1) DEFAULT 0,
    years INT DEFAULT 1,
    INDEX idx_expiry (expiry_date)
);
```

### 3. Setup Cron Job

```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 2 AM)
0 2 * * * /usr/bin/php /path/to/lifecycle_runner.php >> /var/log/nixiepp-lifecycle.log 2>&1
```

### 4. Test

```bash
# Dry run (safe)
php lifecycle_runner.php --dry-run --verbose

# Actual run
php lifecycle_runner.php
```

---

## 📅 NIXI Lifecycle Timeline

```
Day 0:  ⚠️  Domain expires
Day 2:  🔒  Set clientHold (automatic)
Day 1-30:   ✅ Grace period (renew normally)
Day 31-43:  ⚠️  Recovery period (renew with penalty)
Day 44: 🗑️  Delete → Redemption (automatic)
```

---

## 💰 Renewal Rules (CRITICAL)

### During Grace/Recovery (Days 1-43)

| Customer Wants | EPP Call | Why? |
|----------------|----------|------|
| **1 year** | ❌ NO | Registry auto-renews internally |
| **2 years** | ✅ 1 year | First year auto, second via EPP |
| **3 years** | ✅ 2 years | First year auto, rest via EPP |

**Formula**: `EPP_years = requested_years - 1`

### Normal Period (Before/After)

Standard EPP renewal for all requested years.

---

## 🔧 Usage Examples

### In PHP Code

```php
use Box\Mod\Servicedomain\Registrar\NixiEpp\LifecycleService;

// Initialize
$lifecycle = new LifecycleService($eppClient, $logger, $pdo);

// Process all expired domains (cron job)
$lifecycle->processExpiredDomains();

// Handle renewal (after payment)
$lifecycle->handleRenewal(
    'example.in',
    2, // years
    new DateTime('2025-01-01') // current expiry
);
```

### CLI Commands

```bash
# Show help
php lifecycle_runner.php --help

# Dry run (no EPP calls)
php lifecycle_runner.php --dry-run

# Verbose output
php lifecycle_runner.php --verbose

# Both
php lifecycle_runner.php --dry-run --verbose
```

---

## 📊 Status Flow

```
active → clientHold → redemption → (manual restore)
   ↑         ↓
   └── renewed ──┘
```

---

## ⚠️ Important Warnings

1. **DO NOT** automate redemption restore (manual only)
2. **DO NOT** send EPP renew for 1 year during grace/recovery
3. **DO** update local database for auto-renewed domains
4. **DO** run lifecycle processing daily
5. **DO** monitor logs for errors

---

## 🐛 Common Issues

| Problem | Solution |
|---------|----------|
| No domains processed | Check `expiry_date < CURDATE()` |
| EPP errors | Verify credentials in FOSSBilling config |
| Stuck in clientHold | Renew domain to reactivate |
| Slow processing | Reduce BATCH_SIZE in LifecycleService.php |

---

## 📈 Monitoring

### Check Status Distribution

```sql
SELECT status, COUNT(*) as count 
FROM domains 
GROUP BY status;
```

### View Recent Errors

```bash
grep "ERROR" /var/log/nixiepp-lifecycle.log | tail -20
```

### Find Domains Needing Action

```sql
-- Due for clientHold (Day 2)
SELECT domain, expiry_date 
FROM domains 
WHERE DATEDIFF(CURDATE(), expiry_date) = 2;

-- Due for deletion (Day 44)
SELECT domain, expiry_date 
FROM domains 
WHERE DATEDIFF(CURDATE(), expiry_date) = 44;
```

---

## 🎯 Key Methods

| Method | When to Use |
|--------|-------------|
| `processExpiredDomains()` | Cron job (daily) |
| `handleRenewal()` | After customer renews |

---

## 📁 File Locations

| File | Purpose |
|------|---------|
| `LifecycleService.php` | Core engine |
| `lifecycle_runner.php` | CLI script |
| `LIFECYCLE.md` | Full documentation |
| `/var/log/nixiepp-lifecycle.log` | Log file |

---

**Ready to deploy!** 🚀
