# NixiEpp - FOSSBilling Registrar Module

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net/)
[![FOSSBilling](https://img.shields.io/badge/FOSSBilling-Compatible-green.svg)](https://fossbilling.org/)
[![EPP Protocol](https://img.shields.io/badge/EPP-RFC%205730--5734-orange.svg)](https://tools.ietf.org/html/rfc5730)

**Production-ready EPP (Extensible Provisioning Protocol) registrar module for FOSSBilling with TLS encryption and automated domain lifecycle management.**

---

## 🚀 Features

### Domain Management
- ✅ **Domain Registration** - Register new domains via EPP
- ✅ **Domain Transfer** - Transfer domains between registrars
- ✅ **Domain Renewal** - Renew existing domains
- ✅ **Nameserver Management** - Update DNS nameservers
- ✅ **Domain Lock/Unlock** - Prevent unauthorized transfers
- ✅ **Privacy Protection** - WHOIS privacy support
- ✅ **Transfer Codes** - Auth code retrieval
- ✅ **Availability Check** - Check domain availability

### Automated Lifecycle (NIXI .IN Registry)
- ✅ **Auto clientHold** - Automatically set on Day 2 after expiry
- ✅ **Grace Period** - Days 1-30 (normal renewal)
- ✅ **Recovery Period** - Days 31-43 (penalty renewal)
- ✅ **Auto Delete** - Day 44 (send to redemption)
- ✅ **Smart Renewals** - Cost-optimized (registry auto-renew aware)
- ✅ **Batch Processing** - Process 100+ domains efficiently

### Security & Performance
- ✅ **TLS Encryption** - EPP over TLS (RFC 5734)
- ✅ **Mutual Authentication** - Client certificate support
- ✅ **Batch Processing** - Prevent timeouts and memory issues
- ✅ **Error Isolation** - One failure doesn't stop others
- ✅ **Comprehensive Logging** - Full audit trail
- ✅ **Input Sanitization** - XML injection prevention

---

## 📦 Installation

### Requirements

- **PHP**: 8.0 or higher
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **FOSSBilling**: Latest stable version
- **Extensions**: openssl, xml, simplexml, pdo_mysql
- **SSL Certificates**: Client cert, private key, CA bundle

### Quick Install

```bash
# 1. Navigate to FOSSBilling registrar modules directory
cd /path/to/fossbilling/src/modules/Servicedomain/Registrar

# 2. Clone the repository
git clone https://github.com/YOUR_USERNAME/NixiEpp.git

# 3. Set permissions
chown -R www-data:www-data NixiEpp
chmod -R 755 NixiEpp
```

### SSL Certificate Setup

```bash
# Create certificate directory
sudo mkdir -p /etc/fossbilling/ssl

# Copy your certificates
sudo cp client-cert.pem /etc/fossbilling/ssl/
sudo cp client-key.pem /etc/fossbilling/ssl/
sudo cp ca-bundle.crt /etc/fossbilling/ssl/

# Set secure permissions
sudo chmod 600 /etc/fossbilling/ssl/*
sudo chown www-data:www-data /etc/fossbilling/ssl/*
```

### Configure in FOSSBilling

1. Log in to **FOSSBilling Admin Panel**
2. Navigate to **Settings → Domain Management → Registrars**
3. Find **NixiEpp Registrar** and click **Configure**
4. Fill in your EPP server credentials
5. Click **Test Connection**
6. Click **Save Configuration**

---

## 📋 Configuration

### Required Settings

| Setting | Description | Example |
|---------|-------------|---------|
| **EPP Server Host** | EPP server hostname | `epp.registry.com` |
| **EPP Server Port** | EPP server port | `700` |
| **Username** | EPP account username | `registrar123` |
| **Password** | EPP account password | `********` |
| **Object Prefix** | Handle prefix | `NIXI` |

### SSL Configuration

| Setting | Description | Example |
|---------|-------------|---------|
| **SSL Certificate Path** | Client certificate | `/etc/fossbilling/ssl/client-cert.pem` |
| **SSL Key Path** | Client private key | `/etc/fossbilling/ssl/client-key.pem` |
| **SSL CA Path** | CA bundle | `/etc/fossbilling/ssl/ca-bundle.crt` |

---

## 🔄 Domain Lifecycle (NIXI Rules)

```
Day 0:  ⚠️  Domain expires
Day 2:  🔒  Set clientHold (automatic)
Day 1-30:   ✅ Grace period (normal renewal)
Day 31-43:  ⚠️  Recovery period (penalty renewal)
Day 44: 🗑️  Delete → Redemption (automatic)
```

### Setup Cron Job

```bash
# Edit crontab
crontab -e

# Add daily execution at 2 AM
0 2 * * * /usr/bin/php /path/to/fossbilling/src/modules/Servicedomain/Registrar/NixiEpp/lifecycle_runner.php >> /var/log/nixiepp-lifecycle.log 2>&1
```

### Smart Renewal Logic

During grace/recovery period (Days 1-43):
- **Registry auto-renews** for 1 year internally
- **DO NOT send EPP renew** for the first year
- **ONLY renew** additional years via EPP

| Customer Wants | EPP Call | Database Update |
|----------------|----------|-----------------|
| 1 year | ❌ NO | ✅ expiry + 1 year |
| 2 years | ✅ 1 year | ✅ expiry + 2 years |
| 3 years | ✅ 2 years | ✅ expiry + 3 years |

---

## 📁 Project Structure

```
NixiEpp/
├── 📂 Core Module
│   ├── Service.php                    # FOSSBilling adapter
│   ├── EppClient.php                  # TLS transport layer
│   ├── EppFrame.php                   # XML request builder
│   ├── EppResponse.php                # XML response parser
│   └── LifecycleService.php           # Domain lifecycle engine
│
├── 📂 CLI Tools
│   └── lifecycle_runner.php           # Cron job runner
│
├── 📂 Configuration
│   ├── config.html.twig               # Admin configuration UI
│   └── manifest.json.php              # Module metadata
│
├── 📂 Documentation
│   ├── README.md                      # This file
│   ├── INSTALL.md                     # Installation guide
│   ├── API_REFERENCE.md               # API documentation
│   ├── LIFECYCLE.md                   # Lifecycle management guide
│   ├── DEPLOYMENT.md                  # Production deployment checklist
│   └── IMPLEMENTATION_ANALYSIS.md     # Technical analysis
│
└── 📂 Development
    ├── .stubs.php                     # IDE type hints (dev only)
    └── .gitignore                     # Git ignore rules
```

---

## 🧪 Testing

### Test Connection

```bash
# From FOSSBilling Admin Panel
Settings → Domain Management → Registrars → NixiEpp → Test Connection
```

### Dry Run Lifecycle

```bash
# Test without executing EPP commands
php lifecycle_runner.php --dry-run --verbose
```

### Verify Setup

```bash
# Check PHP syntax
php -l Service.php
php -l EppClient.php
php -l LifecycleService.php

# All should report: No syntax errors detected
```

---

## 📊 EPP Commands Supported

| Command | RFC | Status | Description |
|---------|-----|--------|-------------|
| `<login>` | 5730 | ✅ | Authenticate |
| `<logout>` | 5730 | ✅ | Logout |
| `<check>` | 5731 | ✅ | Check availability |
| `<create>` | 5731 | ✅ | Register domain |
| `<info>` | 5731 | ✅ | Get domain info |
| `<update>` | 5731 | ✅ | Modify domain |
| `<renew>` | 5731 | ✅ | Renew domain |
| `<transfer>` | 5731 | ✅ | Transfer domain |
| `<delete>` | 5731 | ✅ | Delete domain |

---

## 🔐 Security

- **TLS 1.2+** encrypted connections
- **Mutual authentication** with client certificates
- **Certificate validation** against CA bundle
- **Input sanitization** prevents XML injection
- **Prepared statements** prevent SQL injection
- **Secure file permissions** for SSL certificates (600)

---

## 📖 Documentation

| Document | Description |
|----------|-------------|
| [INSTALL.md](INSTALL.md) | Step-by-step installation guide |
| [API_REFERENCE.md](API_REFERENCE.md) | Complete API documentation |
| [LIFECYCLE.md](LIFECYCLE.md) | Domain lifecycle management |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Production deployment checklist |
| [IMPLEMENTATION_ANALYSIS.md](IMPLEMENTATION_ANALYSIS.md) | Technical deep dive |

---

## 🐛 Troubleshooting

### Connection Test Fails

1. Verify EPP server host and port
2. Check network connectivity: `telnet epp.registry.com 700`
3. Verify SSL certificate paths and permissions
4. Check firewall rules

### SSL Certificate Errors

```bash
# Test SSL connection
openssl s_client -connect epp.registry.com:700 \
  -cert /etc/fossbilling/ssl/client-cert.pem \
  -key /etc/fossbilling/ssl/client-key.pem \
  -CAfile /etc/fossbilling/ssl/ca-bundle.crt
```

### View Logs

```bash
# Lifecycle logs
tail -f /var/log/nixiepp-lifecycle.log

# FOSSBilling logs
tail -f /path/to/fossbilling/cache/log/fossbilling.log
```

---

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guidelines](CONTRIBUTING.md) first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🆘 Support

- **Documentation**: See documentation files above
- **Issues**: [GitHub Issues](https://github.com/YOUR_USERNAME/NixiEpp/issues)
- **Email**: support@example.com
- **FOSSBilling Forum**: https://forum.fossbilling.org/

---

## 🙏 Acknowledgments

- [FOSSBilling](https://fossbilling.org/) - Open source billing platform
- [IETF](https://www.ietf.org/) - EPP RFC specifications (5730-5734)
- [NIXI](https://registry.in/) - .IN registry lifecycle rules

---

## 📈 Version History

### v1.0.0 (April 17, 2026)

**Initial Release**

- ✅ Complete EPP registrar module
- ✅ TLS-encrypted connections
- ✅ Domain lifecycle management (NIXI rules)
- ✅ Cost-optimized renewal handling
- ✅ Batch processing
- ✅ Comprehensive logging
- ✅ Full documentation

---

**Made with ❤️ for the FOSSBilling community**
