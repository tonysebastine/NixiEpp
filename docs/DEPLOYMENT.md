# Production Deployment Checklist

## Pre-Deployment

### Server Requirements
- [ ] PHP 8.0+ installed
- [ ] PHP extensions enabled: openssl, xml, simplexml
- [ ] MySQL 5.7+ or MariaDB 10.3+
- [ ] FOSSBilling installed and configured
- [ ] SSL/TLS certificates obtained from registry

### SSL Certificates
- [ ] Client certificate file (`.pem`)
- [ ] Client private key file (`.pem`)
- [ ] CA certificate bundle (`.crt`)
- [ ] Certificates stored in secure location (e.g., `/etc/fossbilling/ssl/`)
- [ ] File permissions set to `600`
- [ ] Ownership set to web server user (e.g., `www-data`)

## Installation

### Module Files
- [ ] Copy NixiEpp module to FOSSBilling:
  ```
  /path/to/fossbilling/src/modules/Servicedomain/Registrar/NixiEpp/
  ```
- [ ] Verify all files present:
  - [ ] Service.php
  - [ ] EppClient.php
  - [ ] EppFrame.php
  - [ ] EppResponse.php
  - [ ] manifest.json.php
  - [ ] config.html.twig

### Permissions
- [ ] Set directory ownership: `chown -R www-data:www-data NixiEpp`
- [ ] Set directory permissions: `chmod -R 755 NixiEpp`
- [ ] Verify web server can read all files

## Configuration

### EPP Server Settings
- [ ] EPP Server Host configured
- [ ] EPP Server Port configured (default: 700)
- [ ] Username configured
- [ ] Password configured
- [ ] Object Prefix configured

### TLS/SSL Settings
- [ ] SSL Certificate Path configured (absolute path)
- [ ] SSL Key Path configured (absolute path)
- [ ] SSL CA Certificate Path configured (absolute path)
- [ ] Connection Timeout configured (default: 30s)

### Feature Settings
- [ ] Domain Registration enabled/disabled
- [ ] Domain Transfer enabled/disabled

## Testing

### Connection Test
- [ ] Test connection from FOSSBilling admin panel
- [ ] Verify TLS handshake successful
- [ ] Verify authentication successful
- [ ] Check logs for any errors

### Network Test
- [ ] Test connectivity to EPP server:
  ```bash
  telnet epp.registry.com 700
  ```
- [ ] Test SSL connection:
  ```bash
  openssl s_client -connect epp.registry.com:700 \
    -cert /path/to/client-cert.pem \
    -key /path/to/client-key.pem \
    -CAfile /path/to/ca-bundle.crt
  ```

### Domain Operations Test
- [ ] Check domain availability
- [ ] Register test domain
- [ ] Update nameservers
- [ ] Lock domain
- [ ] Unlock domain
- [ ] Retrieve transfer code
- [ ] Initiate domain transfer
- [ ] Renew domain
- [ ] Get domain information

### Contact Operations Test
- [ ] Create contact
- [ ] Retrieve contact information
- [ ] Verify contact linked to domain

### Error Handling Test
- [ ] Test with invalid credentials
- [ ] Test with invalid domain name
- [ ] Test with unavailable domain
- [ ] Verify errors logged properly
- [ ] Verify user-friendly error messages

## Security

### Access Control
- [ ] Firewall rules configured (restrict EPP server access)
- [ ] SSL certificate files not publicly accessible
- [ ] FOSSBilling admin access restricted
- [ ] Database credentials secure

### Best Practices
- [ ] Strong EPP password used
- [ ] Regular password rotation scheduled
- [ ] Logging enabled and monitored
- [ ] Failed login attempts monitored
- [ ] SSL certificates validity checked
- [ ] Certificate renewal process in place

## Monitoring

### Logging
- [ ] FOSSBilling logs accessible
- [ ] EPP operations logged
- [ ] Error logging enabled
- [ ] Log rotation configured

### Alerts
- [ ] Failed connection attempts monitored
- [ ] Domain operation failures monitored
- [ ] SSL certificate expiration monitored
- [ ] Disk space monitored (for logs)

## Documentation

### Internal Documentation
- [ ] EPP server credentials documented (securely)
- [ ] SSL certificate locations documented
- [ ] Renewal procedures documented
- [ ] Troubleshooting guide available

### Staff Training
- [ ] Staff trained on domain registration process
- [ ] Staff trained on transfer process
- [ ] Staff trained on troubleshooting basics
- [ ] Support procedures documented

## Go-Live

### Final Checks
- [ ] All tests passed
- [ ] Security review completed
- [ ] Backup created
- [ ] Rollback plan prepared
- [ ] Support team notified

### Launch
- [ ] Module activated in production
- [ ] TLDs configured with NixiEpp registrar
- [ ] Pricing configured
- [ ] Test order placed and completed
- [ ] Monitoring active

## Post-Launch

### Verification
- [ ] Real customer orders processing correctly
- [ ] Domain registrations completing
- [ ] Transfers processing
- [ ] Renewals processing
- [ ] No errors in logs

### Optimization
- [ ] Connection pooling considered (if high volume)
- [ ] Timeout values optimized
- [ ] Error handling refined based on real usage
- [ ] Performance monitored

### Maintenance Schedule
- [ ] Regular log reviews scheduled
- [ ] SSL certificate renewal reminders set
- [ ] Module update checks scheduled
- [ ] EPP server maintenance windows known

## Sign-Off

- [ ] **Developer**: Module tested and ready
- [ ] **System Admin**: Server configured and secure
- [ ] **QA**: All tests passed
- [ ] **Manager**: Approved for production

---

**Deployment Date**: _______________  
**Deployed By**: _______________  
**Approved By**: _______________

## Notes

_Add any additional notes or issues encountered during deployment:_

```
                                                                
                                                                
                                                                
```

---

**Version**: 1.0.0  
**Last Updated**: April 17, 2026
