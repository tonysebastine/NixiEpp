# NixiEpp Registrar Module for FOSSBilling

Production-ready EPP (Extensible Provisioning Protocol) registrar module with TLS support for FOSSBilling.

## Features

- ✅ **EPP over TLS** - Secure encrypted connections (RFC 5734)
- ✅ **Domain Registration** - Register new domains
- ✅ **Domain Transfer** - Transfer domains between registrars
- ✅ **Domain Renewal** - Renew existing domains
- ✅ **Domain Management** - Update nameservers, lock/unlock
- ✅ **Contact Management** - Create and manage contacts
- ✅ **Privacy Protection** - WHOIS privacy support
- ✅ **Transfer Codes** - Auth code retrieval
- ✅ **Production Ready** - Error handling, logging, validation

## Requirements

- **PHP**: 8.0 or higher
- **Extensions**: openssl, xml, simplexml
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **FOSSBilling**: Latest version
- **SSL Certificates**: Client certificate, private key, CA bundle (for TLS)

## Installation

### 1. Install Module Files

Copy the NixiEpp module to your FOSSBilling installation:

```bash
# Navigate to your FOSSBilling registrar modules directory
cd /path/to/fossbilling/src/modules/Servicedomain/Registrar

# Copy the module
cp -r /path/to/NixiEpp NixiEpp
```

Or upload via FTP/SFTP:
```
/fossbilling/src/modules/Servicedomain/Registrar/NixiEpp/
├── Service.php
├── EppClient.php
├── EppFrame.php
├── EppResponse.php
├── manifest.json.php
└── config.html.twig
```

### 2. Set Permissions

Ensure proper file permissions:

```bash
# Set ownership (adjust to your web server user)
chown -R www-data:www-data /path/to/fossbilling/src/modules/Servicedomain/Registrar/NixiEpp

# Set permissions
chmod -R 755 /path/to/fossbilling/src/modules/Servicedomain/Registrar/NixiEpp
```

### 3. Configure SSL Certificates

Place your SSL certificates in a secure location:

```bash
# Create certificate directory
mkdir -p /etc/fossbilling/ssl

# Copy certificates (adjust paths as needed)
cp client-cert.pem /etc/fossbilling/ssl/
cp client-key.pem /etc/fossbilling/ssl/
cp ca-bundle.crt /etc/fossbilling/ssl/

# Set secure permissions
chmod 600 /etc/fossbilling/ssl/*
chown www-data:www-data /etc/fossbilling/ssl/*
```

### 4. Activate Module in FOSSBilling

1. Log in to FOSSBilling Admin Panel
2. Navigate to **Settings** → **Domain Management**
3. Click on **Registrars** tab
4. Find **NixiEpp Registrar** in the list
5. Click **Configure**
6. Fill in your EPP server credentials
7. Click **Test Connection** to verify
8. Click **Save Configuration**

## Configuration

### Required Settings

| Setting | Description | Example |
|---------|-------------|---------|
| **EPP Server Host** | EPP server hostname | `epp.registry.com` |
| **EPP Server Port** | EPP server port (default: 700) | `700` |
| **Username** | EPP account username | `registrar123` |
| **Password** | EPP account password | `********` |
| **Object Prefix** | Prefix for handles | `NIXI` |

### Optional Settings

| Setting | Description | Example |
|---------|-------------|---------|
| **New Password** | Change EPP password | `********` |
| **SSL Certificate Path** | Client cert file | `/etc/fossbilling/ssl/client-cert.pem` |
| **SSL Key Path** | Client private key | `/etc/fossbilling/ssl/client-key.pem` |
| **SSL CA Path** | CA certificate bundle | `/etc/fossbilling/ssl/ca-bundle.crt` |
| **Connection Timeout** | Timeout in seconds | `30` |

### Example Configuration

```
EPP Server Host: epp.verisign-grs.com
EPP Server Port: 700
Username: myRegistrar
Password: mySecurePassword
Object Prefix: NIXI

SSL Certificate Path: /etc/fossbilling/ssl/client-cert.pem
SSL Key Path: /etc/fossbilling/ssl/client-key.pem
SSL CA Certificate Path: /etc/fossbilling/ssl/ca-bundle.crt
Connection Timeout: 30
```

## Usage

### Register a New Domain

1. Create a TLD in FOSSBilling
2. Set the registrar to **NixiEpp**
3. Configure pricing
4. Customers can now register domains

### Transfer a Domain

1. Customer provides domain name and auth code
2. FOSSBilling initiates transfer via EPP
3. Monitor transfer status in admin panel

### Manage Domains

- **Update Nameservers**: Change NS records
- **Lock/Unlock**: Prevent unauthorized transfers
- **Privacy Protection**: Enable/disable WHOIS privacy
- **Renew**: Extend domain registration

## Architecture

### Module Structure

```
NixiEpp/
├── Service.php           # Main module service (FOSSBilling integration)
├── EppClient.php         # EPP client with TLS handling
├── EppFrame.php          # EPP XML request builder
├── EppResponse.php       # EPP XML response parser
├── manifest.json.php     # Module manifest
└── config.html.twig      # Configuration template
```

### Class Responsibilities

- **Service.php**: FOSSBilling adapter, business logic
- **EppClient.php**: TCP/TLS connection, send/receive frames
- **EppFrame.php**: Build EPP-compliant XML requests
- **EppResponse.php**: Parse EPP XML responses

### EPP Protocol Flow

```
1. Connect (TLS handshake)
2. Read server greeting
3. Login (authenticate)
4. Send commands (create, update, delete, etc.)
5. Receive responses
6. Logout
7. Disconnect
```

## EPP Commands Supported

### Domain Operations
- ✅ `check` - Check availability
- ✅ `create` - Register domain
- ✅ `info` - Get domain details
- ✅ `update` - Modify nameservers/status
- ✅ `renew` - Renew domain
- ✅ `transfer` - Transfer domain
- ✅ `delete` - Delete domain

### Contact Operations
- ✅ `create` - Create contact
- ✅ `info` - Get contact details
- ✅ `update` - Update contact (future)
- ✅ `delete` - Delete contact (future)

### Host Operations
- ✅ Create via domain (nameservers)
- ⚠️ Standalone host operations (planned)

## Error Handling

The module implements comprehensive error handling:

- Connection failures with retry logic
- Invalid EPP responses
- Timeout handling
- SSL/TLS certificate validation
- Logging of all operations

### Log Locations

Check FOSSBilling logs for EPP operations:
```
FOSSBilling Admin → System → Logs
```

## Troubleshooting

### Connection Test Fails

1. **Check server details**: Verify host and port
2. **Test network connectivity**:
   ```bash
   telnet epp.registry.com 700
   ```
3. **Verify SSL certificates**:
   ```bash
   openssl s_client -connect epp.registry.com:700 -cert client-cert.pem -key client-key.pem
   ```
4. **Check firewall**: Ensure port 700 is not blocked

### SSL Certificate Errors

- Ensure certificate paths are absolute
- Verify file permissions (readable by web server)
- Check certificate validity:
  ```bash
  openssl x509 -in client-cert.pem -text -noout
  ```

### Domain Registration Fails

1. Check EPP server logs
2. Verify contact information is complete
3. Ensure nameservers are valid
4. Review FOSSBilling logs for error details

### Common EPP Result Codes

| Code | Meaning | Action |
|------|---------|--------|
| 1000 | Success | Operation completed |
| 1001 | Pending | Action pending |
| 2001 | Syntax error | Check XML format |
| 2005 | Parameter error | Verify parameters |
| 2201 | Authorization error | Check credentials |
| 2302 | Object exists | Object already created |
| 2303 | Object doesn't exist | Object not found |

## Security Best Practices

1. **Use strong passwords** for EPP account
2. **Keep SSL certificates secure** (chmod 600)
3. **Rotate passwords** regularly
4. **Monitor logs** for suspicious activity
5. **Use firewall rules** to restrict EPP server access
6. **Keep module updated** to latest version

## Development

### Adding New Features

To extend the module:

1. Add new methods to `EppFrame.php` for XML generation
2. Add corresponding methods to `EppClient.php`
3. Update `Service.php` to expose functionality to FOSSBilling

### Registry-Specific Extensions

Some registries require custom extensions:

```php
// Example: Add custom extension to EppFrame.php
public function createCustomExtension($data) {
    return '<extension>
        <custom:extension xmlns:custom="urn:example:custom-1.0">
            <custom:data>' . htmlspecialchars($data) . '</custom:data>
        </custom:extension>
    </extension>';
}
```

## Testing

### Manual Testing

1. Use the **Test Connection** button in admin panel
2. Register a test domain
3. Verify domain appears in registry
4. Test transfer, renewal, and other operations

### Automated Testing (Future)

Unit tests and integration tests will be added in future releases.

## Support

- **Documentation**: https://github.com/nixiepp/docs
- **Issues**: https://github.com/nixiepp/nixiepp-fossbilling/issues
- **Email**: support@nixiepp.example.com

## License

MIT License - See LICENSE file for details

## Changelog

### 1.0.0 (2026-04-17)

- Initial release
- EPP over TLS support
- Domain registration, transfer, renewal
- Contact management
- Nameserver management
- Domain lock/unlock
- Privacy protection support

## Credits

- Developed for FOSSBilling community
- Based on RFC 5730-5734 (EPP specifications)
- Inspired by modern registrar integrations

---

**Version**: 1.0.0  
**Last Updated**: April 17, 2026  
**Author**: NixiEpp Team
