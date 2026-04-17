# API Reference - NixiEpp Registrar Module

## Service Class Methods

### Public API Methods

These methods are called by FOSSBilling's domain management system.

#### `testConnection(): bool`

Tests the connection to the EPP server.

**Returns**: `true` if connection and authentication successful

**Throws**: `Exception` on failure

**Example**:
```php
$registrar = new Service($config);
$connected = $registrar->testConnection();
```

---

#### `registerDomain(Model_Tld $tld, array $domainData): bool`

Registers a new domain name.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Domain registration data:
  - `sld` (string) - Second-level domain
  - `contact_email` (string) - Registrant email
  - `contact_name` (string) - Registrant name
  - `address1` (string) - Street address
  - `city` (string) - City
  - `state` (string) - State/Province
  - `postcode` (string) - Postal code
  - `country` (string) - Country code (ISO 3166-1 alpha-2)
  - `phone` (string) - Phone number (E.164 format)
  - `ns` (array) - Nameservers
  - `registration_period` (int) - Years to register

**Returns**: `true` on success

**Throws**: `Exception` on failure

**Example**:
```php
$domainData = [
    'sld' => 'example',
    'contact_email' => 'admin@example.com',
    'contact_name' => 'John Doe',
    'address1' => '123 Main St',
    'city' => 'New York',
    'state' => 'NY',
    'postcode' => '10001',
    'country' => 'US',
    'phone' => '+1.2125551234',
    'ns' => ['ns1.example.com', 'ns2.example.com'],
    'registration_period' => 1,
];

$registrar->registerDomain($tld, $domainData);
```

---

#### `transferDomain(Model_Tld $tld, array $domainData): bool`

Transfers a domain from another registrar.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Transfer data:
  - `sld` (string) - Second-level domain
  - `auth_code` (string) - Authorization code
  - `transfer_period` (int) - Years to add (default: 1)

**Returns**: `true` on success

**Throws**: `Exception` on failure

---

#### `renewDomain(Model_Tld $tld, array $domainData): bool`

Renews a domain registration.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Renewal data:
  - `sld` (string) - Second-level domain
  - `renew_years` (int) - Years to renew (default: 1)

**Returns**: `true` on success

**Throws**: `Exception` on failure

---

#### `getDomainDetails(Model_Tld $tld, array $domainData): array`

Retrieves domain information.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Domain data:
  - `sld` (string) - Second-level domain

**Returns**: Array containing:
- `name` - Domain name
- `status` - Array of status values
- `registrant` - Contact ID
- `contacts` - Array of contacts
- `nameservers` - Array of nameservers
- `exDate` - Expiration date
- `crDate` - Creation date
- `authInfo` - Authorization code

**Throws**: `Exception` on failure

---

#### `modifyNs(Model_Tld $tld, array $domainData): bool`

Updates domain nameservers.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Data:
  - `sld` (string) - Second-level domain
  - `ns` (array) - New nameservers

**Returns**: `true` on success

**Throws**: `Exception` on failure

---

#### `getTransferCode(Model_Tld $tld, array $domainData): string`

Retrieves domain authorization code.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Data:
  - `sld` (string) - Second-level domain

**Returns**: Authorization code string

**Throws**: `Exception` on failure

---

#### `lockDomain(Model_Tld $tld, array $domainData): bool`

Locks domain to prevent unauthorized transfers.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Data:
  - `sld` (string) - Second-level domain

**Returns**: `true` on success

**Throws**: `Exception` on failure

---

#### `unlockDomain(Model_Tld $tld, array $domainData): bool`

Unlocks domain to allow transfers.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Data:
  - `sld` (string) - Second-level domain

**Returns**: `true` on success

**Throws**: `Exception` on failure

---

#### `enablePrivacyProtection(Model_Tld $tld, array $domainData): bool`

Enables WHOIS privacy protection.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Data:
  - `sld` (string) - Second-level domain

**Returns**: `true` on success

**Throws**: `Exception` on failure

---

#### `disablePrivacyProtection(Model_Tld $tld, array $domainData): bool`

Disables WHOIS privacy protection.

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Data:
  - `sld` (string) - Second-level domain

**Returns**: `true` on success

**Throws**: `Exception` on failure

---

#### `deleteDomain(Model_Tld $tld, array $domainData): bool`

Deletes a domain (if supported by registry).

**Parameters**:
- `$tld` - TLD model object
- `$domainData` - Data:
  - `sld` (string) - Second-level domain

**Returns**: `true` on success

**Throws**: `Exception` on failure

---

#### `isDomainAvailable(string $domainName): bool`

Checks if a domain is available for registration.

**Parameters**:
- `$domainName` - Full domain name (e.g., "example.com")

**Returns**: `true` if available, `false` if taken

**Throws**: `Exception` on failure

---

## EppClient Class Methods

### Connection Management

#### `connect(): bool`

Establishes TLS connection to EPP server.

**Throws**: `Exception` on connection failure

---

#### `disconnect(): void`

Closes connection to EPP server.

---

#### `login(): bool`

Authenticates with EPP server.

**Throws**: `Exception` on authentication failure

---

#### `logout(): bool`

Logs out from EPP server.

---

### Domain Operations

#### `checkDomain(string $domainName): array`

Checks domain availability.

**Returns**: `['available' => bool, 'reason' => string]`

---

#### `createDomain(string $domainName, string $contactId, array $nameservers, int $period, array $domainData): EppResponse`

Creates a new domain.

---

#### `transferDomain(string $domainName, string $authCode, array $domainData): EppResponse`

Initiates domain transfer.

---

#### `renewDomain(string $domainName, int $period): EppResponse`

Renews domain registration.

---

#### `infoDomain(string $domainName): array`

Gets domain information.

---

#### `updateDomainNs(string $domainName, array $nameservers): EppResponse`

Updates domain nameservers.

---

#### `lockDomain(string $domainName): EppResponse`

Adds `clientTransferProhibited` status.

---

#### `unlockDomain(string $domainName): EppResponse`

Removes `clientTransferProhibited` status.

---

#### `deleteDomain(string $domainName): EppResponse`

Deletes domain.

---

### Contact Operations

#### `createContact(string $contactId, array $contactData): string`

Creates a new contact.

**Returns**: Contact ID

---

#### `infoContact(string $contactId): array`

Gets contact information.

---

## Configuration Array Structure

```php
$config = [
    'config' => [
        // Required
        'host' => 'epp.registry.com',
        'port' => 700,
        'username' => 'registrar123',
        'password' => 'secret',
        'prefix' => 'NIXI',
        
        // Optional
        'new_password' => null,
        'ssl_cert_path' => '/path/to/cert.pem',
        'ssl_key_path' => '/path/to/key.pem',
        'ssl_ca_path' => '/path/to/ca-bundle.crt',
        'timeout' => 30,
    ],
];
```

## EPP Result Codes

### Success Codes (1xxx)

| Code | Description |
|------|-------------|
| 1000 | Command completed successfully |
| 1001 | Command completed; action pending |
| 1300 | Command completed successfully; no messages |
| 1301 | Command completed successfully; ack to dequeue |
| 1500 | Command completed successfully; ending session |

### Client Errors (2xxx)

| Code | Description |
|------|-------------|
| 2001 | Command syntax error |
| 2002 | Command use error |
| 2003 | Required parameter missing |
| 2004 | Parameter value range error |
| 2005 | Parameter value syntax error |
| 2101 | Unimplemented protocol version |
| 2102 | Unimplemented command |
| 2103 | Unimplemented option |
| 2104 | Unimplemented extension |
| 2105 | Billing failure |
| 2106 | Object not eligible for renewal |
| 2107 | Object not eligible for transfer |
| 2108 | Authentication error |
| 2201 | Authorization error |
| 2301 | Invalid object status |
| 2302 | Object exists |
| 2303 | Object does not exist |
| 2304 | Object status prohibits operation |
| 2305 | Object association exists |
| 2306 | Object association does not exist |
| 2307 | Parameter value policy error |
| 2308 | Unimplemented object service |
| 2400 | Command failed |
| 2500 | Command failed; server closing connection |
| 2501 | Authentication error; server closing connection |
| 2502 | Session limit exceeded; server closing connection |

### Server Errors (2xxx)

| Code | Description |
|------|-------------|
| 2001-2502 | See client errors above |

## Error Handling

All methods throw `Exception` with descriptive messages on failure.

**Example**:
```php
try {
    $registrar->registerDomain($tld, $domainData);
} catch (Exception $e) {
    // Log error
    error_log('Domain registration failed: ' . $e->getMessage());
    
    // Handle error
    echo 'Registration failed: ' . $e->getMessage();
}
```

## Logging

The module uses FOSSBilling's logging system:

- **info**: Successful operations
- **err**: Errors and failures
- **crit**: Critical failures (connection issues)

Access logs via: FOSSBilling Admin → System → Logs

---

**Version**: 1.0.0  
**Last Updated**: April 17, 2026
