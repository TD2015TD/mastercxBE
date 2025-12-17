# System Configuration Guide

This guide explains how to use the database-driven system configuration feature for managing email and authentication settings.

## Overview

The system configuration module allows you to store and manage application settings in the database instead of environment variables. This includes:

- **Email/SMTP Configuration** - Dynamic mail settings
- **Authentication Methods** - Database or LDAP authentication
- **General System Settings** - Timezone, date formats, etc.

## Installation Steps

### 1. Run Migrations

```bash
php artisan migrate
```

This will create the `system_configurations` table.

### 2. Seed Default Configurations

```bash
php artisan db:seed --class=SystemConfigurationSeeder
```

This will populate the database with default configurations including your Gmail SMTP settings.

### 3. Clear Configuration Cache

```bash
php artisan config:clear
php artisan cache:clear
```

## Features

### Email Configuration

The system supports dynamic email configuration stored in the database. Settings include:

- **Mail Driver**: smtp, sendmail, mailgun, etc.
- **SMTP Host**: smtp.gmail.com
- **SMTP Port**: 465
- **Encryption**: ssl/tls
- **Username**: Your email address
- **Password**: Encrypted app password
- **From Address**: Default sender email
- **From Name**: Default sender name

### Authentication Methods

Two authentication methods are supported:

1. **Database Authentication** (default)
   - Standard email/password authentication
   - User credentials stored in the database

2. **LDAP Authentication**
   - Authenticate against an LDAP/Active Directory server
   - Automatically sync users to local database
   - Configurable LDAP connection settings

## API Endpoints

All endpoints require JWT authentication (`jwt.verify` middleware).

### Get All Configurations

```http
GET /api/v1/system/configurations
```

Returns all configurations grouped by category.

### Get Configurations by Category

```http
GET /api/v1/system/configurations/category/{category}
```

Categories: `mail`, `auth`, `ldap`, `general`

Example:
```http
GET /api/v1/system/configurations/category/mail
```

### Get Single Configuration

```http
GET /api/v1/system/configurations/{id}
```

### Update Configuration

```http
PUT /api/v1/system/configurations/{id}
Content-Type: application/json

{
  "value": "new_value",
  "is_active": true
}
```

### Bulk Update Configurations

```http
POST /api/v1/system/configurations/bulk-update
Content-Type: application/json

{
  "configurations": [
    {
      "key": "mail.host",
      "value": "smtp.gmail.com"
    },
    {
      "key": "mail.port",
      "value": "465"
    },
    {
      "key": "mail.username",
      "value": "mail@apextreasuresoftware.com"
    },
    {
      "key": "mail.password",
      "value": "elkb nnor xrlp shos"
    }
  ]
}
```

### Test Email Configuration

```http
POST /api/v1/system/configurations/test-mail
Content-Type: application/json

{
  "test_email": "recipient@example.com"
}
```

Sends a test email to verify SMTP settings are working.

### Test LDAP Connection

```http
POST /api/v1/system/configurations/test-ldap
```

Tests the LDAP connection with current settings.

### Clear Configuration Cache

```http
POST /api/v1/system/configurations/clear-cache
```

Clears all cached configurations. The system will reload from the database.

## Configuration Keys

### Mail Configuration

| Key | Description | Type | Encrypted |
|-----|-------------|------|-----------|
| `mail.driver` | Mail driver (smtp, sendmail) | string | No |
| `mail.host` | SMTP server host | string | No |
| `mail.port` | SMTP server port | integer | No |
| `mail.encryption` | Encryption method (ssl, tls) | string | No |
| `mail.username` | SMTP username | string | No |
| `mail.password` | SMTP password | string | **Yes** |
| `mail.from.address` | Default from email | string | No |
| `mail.from.name` | Default from name | string | No |

### Authentication Configuration

| Key | Description | Type | Encrypted |
|-----|-------------|------|-----------|
| `auth.method` | Auth method (database, ldap) | string | No |
| `auth.allow_registration` | Allow user registration | boolean | No |
| `auth.require_email_verification` | Require email verification | boolean | No |

### LDAP Configuration

| Key | Description | Type | Encrypted |
|-----|-------------|------|-----------|
| `ldap.host` | LDAP server host | string | No |
| `ldap.port` | LDAP server port | integer | No |
| `ldap.base_dn` | LDAP base DN | string | No |
| `ldap.username` | LDAP bind username | string | No |
| `ldap.password` | LDAP bind password | string | **Yes** |
| `ldap.use_ssl` | Use SSL for connection | boolean | No |
| `ldap.user_search_attribute` | User search attribute (uid, cn) | string | No |

## Usage Examples

### Switching to LDAP Authentication

1. Update LDAP configuration:

```http
POST /api/v1/system/configurations/bulk-update

{
  "configurations": [
    {
      "key": "ldap.host",
      "value": "ldap.yourcompany.com"
    },
    {
      "key": "ldap.port",
      "value": "389"
    },
    {
      "key": "ldap.base_dn",
      "value": "dc=yourcompany,dc=com"
    },
    {
      "key": "ldap.username",
      "value": "cn=admin,dc=yourcompany,dc=com"
    },
    {
      "key": "ldap.password",
      "value": "your_ldap_password"
    }
  ]
}
```

2. Test LDAP connection:

```http
POST /api/v1/system/configurations/test-ldap
```

3. Switch authentication method:

```http
PUT /api/v1/system/configurations/{id}

{
  "value": "ldap"
}
```

Where `{id}` is the ID of the `auth.method` configuration.

4. Clear cache:

```http
POST /api/v1/system/configurations/clear-cache
```

### Updating Email Settings

```http
POST /api/v1/system/configurations/bulk-update

{
  "configurations": [
    {
      "key": "mail.host",
      "value": "smtp.gmail.com"
    },
    {
      "key": "mail.port",
      "value": "465"
    },
    {
      "key": "mail.encryption",
      "value": "ssl"
    },
    {
      "key": "mail.username",
      "value": "mail@apextreasuresoftware.com"
    },
    {
      "key": "mail.password",
      "value": "elkb nnor xrlp shos"
    }
  ]
}
```

## Code Usage

### Get a Configuration Value

```php
use App\Models\SystemConfiguration;

// Get a single configuration value
$mailHost = SystemConfiguration::get('mail.host', 'default_value');

// Get all mail configurations
$mailConfig = SystemConfiguration::getMailConfig();

// Get all auth configurations
$authConfig = SystemConfiguration::getAuthConfig();

// Get configurations by category
$generalConfig = SystemConfiguration::getByCategory('general');
```

### Set a Configuration Value

```php
use App\Models\SystemConfiguration;

SystemConfiguration::set(
    key: 'mail.host',
    value: 'smtp.gmail.com',
    category: 'mail',
    type: 'string',
    isEncrypted: false
);

// Set encrypted value (e.g., password)
SystemConfiguration::set(
    key: 'mail.password',
    value: 'your_password',
    category: 'mail',
    type: 'string',
    isEncrypted: true
);
```

## Security Notes

1. **Encrypted Fields**: Passwords and sensitive data are automatically encrypted using Laravel's encryption.
2. **API Protection**: All configuration endpoints are protected with JWT authentication.
3. **Cache**: Configurations are cached for 1 hour to improve performance.
4. **Masked Display**: Encrypted values are masked with `********` in API responses.

## Troubleshooting

### Email not sending

1. Test the mail configuration:
   ```http
   POST /api/v1/system/configurations/test-mail
   {
     "test_email": "your@email.com"
   }
   ```

2. Check configuration values:
   ```http
   GET /api/v1/system/configurations/category/mail
   ```

3. Clear cache:
   ```http
   POST /api/v1/system/configurations/clear-cache
   ```

### LDAP authentication not working

1. Test LDAP connection:
   ```http
   POST /api/v1/system/configurations/test-ldap
   ```

2. Check LDAP configuration:
   ```http
   GET /api/v1/system/configurations/category/ldap
   ```

3. Verify `auth.method` is set to `ldap`:
   ```http
   GET /api/v1/system/configurations/category/auth
   ```

4. Check application logs for detailed error messages

## Migration from .env

Your existing `.env` settings have been preserved and seeded into the database. The system now loads configuration from the database first, falling back to `.env` values if database configuration is not available.

To fully migrate to database configuration:

1. Run the seeder to populate default values
2. Update any custom values via the API
3. Keep `.env` values as fallback for local development

## Admin UI Integration

You can build an admin interface to manage these configurations with the following features:

1. **Configuration Categories**: Tabs for Mail, Auth, LDAP, General
2. **Edit Forms**: Update configuration values
3. **Test Buttons**: Test mail and LDAP connections
4. **Toggle Active**: Enable/disable specific configurations
5. **Clear Cache**: Button to clear configuration cache after updates
