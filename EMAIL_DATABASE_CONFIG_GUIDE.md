# Email Configuration - Database Management Guide

This guide explains how to manage email configurations (SMTP and IMAP) via the database instead of environment variables.

## Overview

The system supports storing multiple email configurations in the database, allowing you to:
- Manage multiple email accounts from a single interface
- Configure different departments with different email addresses
- Switch configurations without modifying code or .env files
- Test connections before activating
- Set priorities and auto-assignment rules per email account

## Architecture

```
Database → EmailConfiguration Model → ImapEmailService/SMTP → Email Processing
```

### Benefits

✅ **Multi-Account Support** - Handle multiple email addresses
✅ **Department Routing** - Route emails to specific departments
✅ **GUI Management** - Configure via API without server access
✅ **Encrypted Passwords** - Credentials stored encrypted in database
✅ **Fallback Support** - Falls back to .env if no DB config exists
✅ **Connection Testing** - Test SMTP/IMAP before activation

## Database Schema

### `email_configurations` Table

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| name | string | Configuration name (e.g., "Support Email") |
| type | enum | smtp, imap, or both |
| is_active | boolean | Enable/disable configuration |
| is_default | boolean | Default configuration for type |
| smtp_host | string | SMTP server hostname |
| smtp_port | integer | SMTP port (usually 587 or 465) |
| smtp_encryption | string | ssl, tls, or null |
| smtp_username | string | SMTP username |
| smtp_password | text | SMTP password (encrypted) |
| smtp_from_address | string | From email address |
| smtp_from_name | string | From name |
| imap_host | string | IMAP server hostname |
| imap_port | integer | IMAP port (usually 993) |
| imap_encryption | string | ssl, tls, or null |
| imap_username | string | IMAP username |
| imap_password | text | IMAP password (encrypted) |
| imap_folder | string | IMAP folder to monitor (default: INBOX) |
| department | string | Department for auto-routing |
| auto_assign | boolean | Enable auto-assignment to agents |
| default_priority | string | Default ticket priority (P1-P4) |
| settings | json | Additional custom settings |

## Installation & Setup

### 1. Run Migration

```bash
php artisan migrate
```

This creates the `email_configurations` table.

### 2. Create Your First Configuration

Via API (recommended):

```http
POST /api/v1/email-configurations
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Support Email",
  "type": "both",
  "is_active": true,
  "is_default": true,

  "smtp_host": "smtp.gmail.com",
  "smtp_port": 587,
  "smtp_encryption": "tls",
  "smtp_username": "support@example.com",
  "smtp_password": "your-app-password",
  "smtp_from_address": "support@example.com",
  "smtp_from_name": "Support Team",

  "imap_host": "imap.gmail.com",
  "imap_port": 993,
  "imap_encryption": "ssl",
  "imap_username": "support@example.com",
  "imap_password": "your-app-password",
  "imap_folder": "INBOX",

  "department": "Support",
  "auto_assign": true,
  "default_priority": "P3"
}
```

Or via database:

```sql
INSERT INTO email_configurations (
  name, type, is_active, is_default,
  imap_host, imap_port, imap_encryption,
  imap_username, imap_password, imap_folder,
  department, auto_assign, default_priority,
  created_at, updated_at
) VALUES (
  'Support Email', 'imap', true, true,
  'imap.gmail.com', 993, 'ssl',
  'support@example.com', 'encrypted_password', 'INBOX',
  'Support', true, 'P3',
  NOW(), NOW()
);
```

## API Endpoints

### List All Configurations

```http
GET /api/v1/email-configurations
```

Query parameters:
- `type` - Filter by type (smtp, imap, both)
- `is_active` - Filter by active status (true/false)
- `sort_by` - Sort field (default: created_at)
- `sort_order` - Sort order (asc/desc)
- `per_page` - Results per page (default: 15)

### Get Single Configuration

```http
GET /api/v1/email-configurations/{id}
```

### Create Configuration

```http
POST /api/v1/email-configurations
Content-Type: application/json

{
  "name": "Sales Email",
  "type": "both",
  "is_active": true,
  "is_default": false,
  "imap_host": "imap.gmail.com",
  "imap_port": 993,
  "imap_encryption": "ssl",
  "imap_username": "sales@example.com",
  "imap_password": "password",
  "imap_folder": "INBOX",
  "department": "Sales",
  "auto_assign": true,
  "default_priority": "P2"
}
```

### Update Configuration

```http
PUT /api/v1/email-configurations/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "department": "Customer Success",
  "default_priority": "P1"
}
```

Note: Passwords are optional in updates. Omit them to keep existing passwords.

### Delete Configuration

```http
DELETE /api/v1/email-configurations/{id}
```

### Test SMTP Connection

```http
POST /api/v1/email-configurations/{id}/test-smtp
```

Response:
```json
{
  "success": true,
  "message": "SMTP connection successful"
}
```

### Test IMAP Connection

```http
POST /api/v1/email-configurations/{id}/test-imap
```

Response:
```json
{
  "success": true,
  "message": "IMAP connection successful"
}
```

### Set as Default

```http
POST /api/v1/email-configurations/{id}/set-default
```

Only one configuration per type can be default. This will unset others.

### Toggle Active Status

```http
POST /api/v1/email-configurations/{id}/toggle-active
```

### Get Active IMAP Configurations

```http
GET /api/v1/email-configurations/active-imap
```

Returns all active IMAP configurations for processing.

## Usage Examples

### Example 1: Multiple Department Email Accounts

```json
[
  {
    "name": "Support Email",
    "imap_username": "support@company.com",
    "department": "Support",
    "default_priority": "P3",
    "is_active": true
  },
  {
    "name": "Sales Email",
    "imap_username": "sales@company.com",
    "department": "Sales",
    "default_priority": "P2",
    "is_active": true
  },
  {
    "name": "Billing Email",
    "imap_username": "billing@company.com",
    "department": "Finance",
    "default_priority": "P1",
    "is_active": true
  }
]
```

The system will check all active IMAP accounts and route tickets to their respective departments.

### Example 2: Testing Before Activation

```bash
# 1. Create configuration (inactive)
curl -X POST https://api.example.com/v1/email-configurations \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "name": "New Account",
    "type": "imap",
    "is_active": false,
    "imap_host": "imap.gmail.com",
    ...
  }'

# 2. Test connection
curl -X POST https://api.example.com/v1/email-configurations/1/test-imap \
  -H "Authorization: Bearer TOKEN"

# 3. Activate if test passes
curl -X POST https://api.example.com/v1/email-configurations/1/toggle-active \
  -H "Authorization: Bearer TOKEN"
```

### Example 3: Gmail Setup

For Gmail accounts:

```json
{
  "name": "Gmail Support",
  "type": "both",

  "smtp_host": "smtp.gmail.com",
  "smtp_port": 587,
  "smtp_encryption": "tls",
  "smtp_username": "your-email@gmail.com",
  "smtp_password": "your-16-char-app-password",

  "imap_host": "imap.gmail.com",
  "imap_port": 993,
  "imap_encryption": "ssl",
  "imap_username": "your-email@gmail.com",
  "imap_password": "your-16-char-app-password",
  "imap_folder": "INBOX"
}
```

**Important:** Use App Passwords, not your regular Gmail password.

### Example 4: Microsoft 365 Setup

```json
{
  "name": "Office 365 Support",
  "type": "both",

  "smtp_host": "smtp.office365.com",
  "smtp_port": 587,
  "smtp_encryption": "tls",
  "smtp_username": "support@yourdomain.com",
  "smtp_password": "your-password",

  "imap_host": "outlook.office365.com",
  "imap_port": 993,
  "imap_encryption": "ssl",
  "imap_username": "support@yourdomain.com",
  "imap_password": "your-password",
  "imap_folder": "INBOX"
}
```

## How It Works

### Email Processing Flow

1. **Scheduled Task** runs every minute (configured in `Kernel.php`)
2. **ProcessIncomingEmail Command** executes:
   - Queries database for active IMAP configurations
   - If none found, falls back to `.env` configuration
   - Processes emails from each active account
3. **ImapEmailService** connects using DB credentials
4. **Tickets Created** with department and priority from config
5. **Agents Notified** based on auto_assign setting

### Priority System

Priority is determined in this order:
1. **Keyword Detection** - Checks email for urgent keywords
2. **Config Default** - Uses `default_priority` from configuration
3. **System Default** - Falls back to P3 if nothing else set

### Auto-Assignment

When `auto_assign` is `true`:
- System finds available agents in the configured department
- Assigns ticket to least busy agent
- Creates notification for assigned agent

When `auto_assign` is `false`:
- Ticket remains unassigned
- Appears in unassigned queue
- Supervisor can manually assign

## Security

### Password Encryption

Passwords are automatically encrypted when stored:

```php
// Model handles encryption/decryption automatically
$config->smtp_password = 'plain-password'; // Stored encrypted
echo $config->smtp_password; // Returns decrypted
```

Encryption uses Laravel's encryption (`encrypt()`/`decrypt()`), which uses:
- AES-256-CBC cipher
- Application key from `APP_KEY` in `.env`

### Best Practices

1. **Never commit .env** - Keeps `APP_KEY` secure
2. **Use app passwords** - For Gmail/Microsoft 365
3. **Restrict API access** - Require admin role for email config endpoints
4. **Rotate credentials** - Periodically update passwords
5. **Monitor access logs** - Track who modifies configurations

## Migration from .env

To migrate from environment variables to database:

### Option 1: Via API

```bash
# Create config from existing .env values
curl -X POST https://api.example.com/v1/email-configurations \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "name": "Main Account",
    "type": "both",
    "is_active": true,
    "is_default": true,
    "imap_host": "'$IMAP_HOST'",
    "imap_port": '$IMAP_PORT',
    "imap_encryption": "'$IMAP_ENCRYPTION'",
    "imap_username": "'$IMAP_USERNAME'",
    "imap_password": "'$IMAP_PASSWORD'",
    "imap_folder": "'$IMAP_FOLDER'"
  }'
```

### Option 2: Via Artisan Command (Create One)

```php
// database/seeders/EmailConfigurationSeeder.php
public function run()
{
    EmailConfiguration::create([
        'name' => 'Main Account',
        'type' => 'both',
        'is_active' => true,
        'is_default' => true,
        'imap_host' => env('IMAP_HOST'),
        'imap_port' => env('IMAP_PORT'),
        'imap_encryption' => env('IMAP_ENCRYPTION'),
        'imap_username' => env('IMAP_USERNAME'),
        'imap_password' => env('IMAP_PASSWORD'),
        'imap_folder' => env('IMAP_FOLDER'),
        'department' => 'Support',
        'auto_assign' => true,
        'default_priority' => 'P3',
    ]);
}
```

Run:
```bash
php artisan db:seed --class=EmailConfigurationSeeder
```

## Troubleshooting

### No Emails Being Processed

**Check:**
1. Are there active configurations?
   ```sql
   SELECT * FROM email_configurations WHERE is_active = true;
   ```
2. Is the scheduler running?
   ```bash
   php artisan schedule:list
   ```
3. Check logs:
   ```bash
   tail -f storage/logs/laravel.log | grep -i email
   ```

### Connection Failed

**Test the configuration:**
```http
POST /api/v1/email-configurations/{id}/test-imap
```

Common issues:
- Wrong port (993 for IMAP SSL, 143 for IMAP TLS)
- Incorrect encryption setting
- Firewall blocking outbound connections
- App password not enabled (Gmail/Microsoft)

### Multiple Tickets from Same Email

**Issue:** Duplicate processing

**Solution:**
- Email processor uses `withoutOverlapping()` in scheduler
- Check if multiple cron jobs are running
- Verify only one email processor instance active

### Department Not Being Set

**Check configuration:**
```sql
SELECT id, name, department, is_active
FROM email_configurations
WHERE is_active = true;
```

If `department` is NULL, tickets will use "Support" as default.

## Advanced Configuration

### Custom Settings Field

The `settings` JSON field allows custom configuration:

```json
{
  "custom_fields": {
    "cc_addresses": ["manager@example.com"],
    "signature": "Best regards,\nSupport Team",
    "max_attachment_size": 10485760
  },
  "processing_rules": {
    "skip_auto_reply": true,
    "filter_spam": true
  }
}
```

Access in code:
```php
$ccAddresses = $config->settings['custom_fields']['cc_addresses'] ?? [];
```

### Environment Fallback

The system gracefully falls back to `.env` if no database configs exist:

```php
// 1. Try database config
$config = EmailConfiguration::getDefaultImap();

// 2. If null, use .env values
if (!$config) {
    $host = config('mail.imap.host');
    // ...
}
```

This allows gradual migration without breaking existing setups.

## Monitoring & Logs

### Check Processing Status

```sql
-- Count emails processed per configuration
SELECT
  ec.name,
  COUNT(em.id) as email_count,
  COUNT(CASE WHEN em.is_processed THEN 1 END) as processed,
  COUNT(CASE WHEN em.ticket_id IS NOT NULL THEN 1 END) as tickets_created
FROM email_configurations ec
LEFT JOIN email_messages em ON em.source LIKE CONCAT('%', ec.name, '%')
WHERE ec.is_active = true
GROUP BY ec.id;
```

### Configuration Audit Log

Track who changes configurations:

```sql
-- Get recent configuration changes
SELECT * FROM email_configurations
ORDER BY updated_at DESC
LIMIT 10;
```

## Performance Optimization

### For High Volume

1. **Increase check frequency:**
   ```php
   // app/Console/Kernel.php
   $schedule->command('email:process --once')
       ->everyThirtySeconds() // Instead of every minute
       ->withoutOverlapping();
   ```

2. **Use queue workers:**
   ```php
   // Move ticket creation to queue
   dispatch(new ProcessEmailJob($emailData, $config));
   ```

3. **Index optimization:**
   ```sql
   CREATE INDEX idx_active_imap ON email_configurations(type, is_active, is_default);
   ```

## Related Documentation

- [EMAIL_INTEGRATION_GUIDE.md](EMAIL_INTEGRATION_GUIDE.md) - General email setup
- Main README - Overall system documentation

---

**Last Updated:** 2025-12-29
**Version:** 1.0.0
