# Email Integration Guide - IMAP Ticket Creation

This guide explains how the CRM system automatically creates tickets from incoming emails using IMAP integration.

## Overview

The email integration system consists of:
1. **IMAP Email Listener** - Connects to email server and downloads new messages
2. **Email Deduplication** - Prevents duplicate tickets from the same email
3. **Automatic Ticket Creation** - Creates tickets from new emails
4. **Thread Management** - Links email replies to existing tickets
5. **Notification System** - Notifies agents of new email tickets

## Architecture

```
IMAP Server → ImapEmailService → ProcessIncomingEmail Command → Ticket Creation → Agent Notification
```

### Components

#### 1. EmailMessage Model (`app/Models/EmailMessage.php`)
Stores all incoming email messages with:
- Message headers (from, to, subject, etc.)
- Email body (text and HTML)
- Attachments metadata
- Thread information for conversation tracking
- Link to created ticket

#### 2. ImapEmailService (`app/Services/ImapEmailService.php`)
Handles IMAP operations:
- Connects to IMAP server
- Fetches unread emails
- Parses email structure
- Extracts attachments
- Manages email threading

#### 3. ProcessIncomingEmail Command (`app/Console/Commands/ProcessIncomingEmail.php`)
Processes emails and creates tickets:
- Finds or creates customer by email
- Checks for duplicate emails (by message_id)
- Detects if email is a reply to existing ticket
- Creates new ticket or updates existing one
- Sends notifications to assigned agents

## Configuration

### 1. Environment Setup

Add these variables to your `.env` file:

```env
# IMAP Email Configuration
IMAP_HOST=imap.gmail.com
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USERNAME=support@example.com
IMAP_PASSWORD=your-app-password
IMAP_FOLDER=INBOX
```

### 2. Gmail Configuration (Example)

For Gmail accounts:
1. Enable IMAP in Gmail settings
2. Create an App Password (Settings → Security → 2-Step Verification → App passwords)
3. Use the app password in `IMAP_PASSWORD`

### 3. Microsoft 365 / Outlook Configuration (Example)

```env
IMAP_HOST=outlook.office365.com
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USERNAME=support@yourdomain.com
IMAP_PASSWORD=your-password
IMAP_FOLDER=INBOX
```

## Installation

### 1. Run Migration

```bash
php artisan migrate
```

This creates the `email_messages` table.

### 2. Verify PHP IMAP Extension

The IMAP service requires the PHP IMAP extension:

```bash
# Check if IMAP is installed
php -m | grep imap

# On Ubuntu/Debian
sudo apt-get install php-imap
sudo systemctl restart php-fpm

# On macOS with Homebrew
brew install imap-uw
pecl install imap
```

### 3. Test Connection

Run the email processor once to test:

```bash
php artisan email:process --once
```

## Usage

### Running the Email Processor

#### Option 1: Scheduled Task (Recommended)

The email processor is already configured in `app/Console/Kernel.php` to run every minute via Laravel's scheduler.

Start the scheduler:

```bash
php artisan schedule:work
```

Or add to crontab:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

#### Option 2: Continuous Daemon

Run as a continuous process that checks every 60 seconds:

```bash
php artisan email:process
```

To change the check interval:

```bash
php artisan email:process --interval=30  # Check every 30 seconds
```

#### Option 3: Manual Processing

Process emails once and exit:

```bash
php artisan email:process --once
```

### Running as a System Service (Production)

Create a systemd service file `/etc/systemd/system/crm-email-processor.service`:

```ini
[Unit]
Description=CRM Email Processor
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/crm-api
ExecStart=/usr/bin/php /var/www/crm-api/artisan email:process
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl enable crm-email-processor
sudo systemctl start crm-email-processor
sudo systemctl status crm-email-processor
```

## Features

### 1. Email Deduplication

The system prevents duplicate ticket creation by:
- Checking the `message_id` header (unique per email)
- Storing processed emails in the database
- Skipping emails that already exist

### 2. Thread Detection & Reply Handling

When a customer replies to a ticket notification:
- System extracts ticket number from subject line (e.g., `RE: [TKT20231215ABC123]`)
- Or uses email headers (`In-Reply-To`, `References`) to track conversation threads
- Updates existing ticket instead of creating a new one
- Reopens closed tickets if customer sends new reply

### 3. Automatic Customer Creation

If an email comes from an unknown address:
- New customer record is created automatically
- Email address becomes the customer identifier
- Name is extracted from email headers

### 4. Priority Detection

The system analyzes subject and body for keywords:
- **P1 (Critical)**: urgent, critical, emergency, asap, immediately
- **P3 (Medium)**: Default for other emails

### 5. Attachment Handling

Email attachments are:
- Extracted and stored in `storage/email_attachments/YYYY/MM/DD/`
- Metadata saved in `attachments` JSON field
- Accessible via the ticket

### 6. Agent Assignment

Tickets created from emails are:
- Auto-assigned to available agents
- Department-based if configured
- Triggers notification to assigned agent

### 7. Notifications

When an email ticket is created:
- Assigned agent receives database notification
- Notification includes ticket number, priority, and link
- Visible in the agent's notification panel

## Email Format Best Practices

### For Customers

Encourage customers to:
1. **Keep ticket number in subject** when replying (e.g., `RE: Support Request [TKT20231215ABC123]`)
2. **Reply to notification emails** instead of creating new emails
3. **Use descriptive subject lines**

### For System (Auto-Reply)

Configure auto-reply to customers with:
- Ticket number in subject: `[TKT20231215ABC123] Your Support Request`
- Clear instructions to keep ticket number when replying
- Reference number in email body

Example auto-reply template:

```
Subject: [TKT20231215ABC123] Support Request Received

Dear Customer,

Your support request has been received and assigned ticket number: TKT20231215ABC123

Please include this ticket number in the subject line when replying to this email.

Our team will respond within [SLA timeframe].

Thank you,
Support Team
```

## Monitoring & Logs

### Check Email Processing Logs

```bash
# Real-time logs
php artisan pail

# Or check log files
tail -f storage/logs/laravel.log | grep -i email
```

### Common Log Messages

- `IMAP connection established successfully` - Connection successful
- `Found X new email(s)` - New emails detected
- `Created new ticket: TKT...` - Ticket created from email
- `Updated existing ticket: TKT...` - Email reply processed
- `Email ... already processed, skipping` - Duplicate email skipped

### Monitoring Email Processing

Check the `email_messages` table:

```sql
-- Recent emails processed
SELECT id, from_email, subject, is_processed, created_at
FROM email_messages
ORDER BY created_at DESC
LIMIT 10;

-- Unprocessed emails
SELECT * FROM email_messages WHERE is_processed = false;

-- Emails by date
SELECT DATE(created_at) as date, COUNT(*) as count
FROM email_messages
GROUP BY DATE(created_at);
```

## Troubleshooting

### IMAP Connection Failed

```
Error: Failed to connect to IMAP server: ...
```

**Solutions:**
1. Verify IMAP credentials in `.env`
2. Check if IMAP is enabled on email server
3. Verify firewall allows outbound connections on port 993
4. Test connection manually: `telnet imap.gmail.com 993`

### PHP IMAP Extension Not Found

```
Error: Call to undefined function imap_open()
```

**Solution:** Install PHP IMAP extension (see Installation section)

### Duplicate Tickets Created

**Possible causes:**
- Email processor running multiple times simultaneously
- Message-ID not being captured properly

**Solutions:**
- Use `withoutOverlapping()` in scheduler (already configured)
- Check database for duplicate `message_id` entries

### Tickets Not Creating

**Debug steps:**
1. Run with verbose logging: `php artisan email:process --once -vvv`
2. Check if emails are being fetched but not processed
3. Verify database permissions
4. Check for exceptions in logs

### Emails Marked as Read but No Ticket

**Possible causes:**
- Exception during ticket creation
- Validation errors

**Solutions:**
- Check `email_messages` table for records with `is_processed = false`
- Review logs for error messages
- Verify customer email addresses are valid

## API Endpoints

### Create Notification for Email Ticket

```http
POST /api/v1/notifications/email
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 1,
  "ticket_id": 123,
  "ticket_number": "TKT20231215ABC123",
  "type": "email_ticket_created",
  "message": "New ticket from email",
  "priority": "P3",
  "department": "Support"
}
```

### Create Ticket from Email (Internal)

```http
POST /api/v1/tickets/from-email
Authorization: Bearer {token}
Content-Type: application/json

{
  "email_message_id": 456,
  "customer_id": 789,
  "subject": "Support Request",
  "description": "Email body content...",
  "priority": "P3",
  "department": "Support"
}
```

## Database Schema

### `email_messages` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| message_id | string | Unique email message ID (deduplication) |
| subject | string | Email subject |
| from_email | string | Sender email address |
| from_name | string | Sender name |
| to_email | string | Recipient email |
| body_text | text | Plain text body |
| body_html | text | HTML body |
| received_at | timestamp | Email received timestamp |
| ticket_id | foreign key | Associated ticket (nullable) |
| customer_id | foreign key | Associated customer (nullable) |
| is_processed | boolean | Processing status |
| processed_at | timestamp | Processing timestamp |
| attachments | json | Attachment metadata |
| conversation_thread_id | string | Thread tracking |
| in_reply_to | string | Reply-to header |
| references | text | Email references header |

## Performance Considerations

### Email Volume

- **Low volume** (< 100 emails/day): Run every minute (default)
- **Medium volume** (100-1000 emails/day): Run every 30 seconds
- **High volume** (> 1000 emails/day): Run continuous daemon or multiple workers

### Optimization Tips

1. **Limit email fetch**: Configure email rules to filter only relevant emails to support inbox
2. **Archive processed emails**: Move processed emails to subfolder to reduce IMAP query time
3. **Database indexing**: Indexes already created on `message_id`, `is_processed`, `from_email`
4. **Queue processing**: For high volume, consider moving ticket creation to queue workers

## Security Considerations

1. **Store credentials securely**: Never commit `.env` file
2. **Use app passwords**: For Gmail/Microsoft 365, use app-specific passwords
3. **Validate email content**: System sanitizes email bodies before storage
4. **Limit attachment sizes**: Configure in `ImapEmailService.php`
5. **Monitor for spam**: Consider implementing spam filtering

## Future Enhancements

Potential improvements:
- [ ] Email templates for auto-replies
- [ ] Rich text email responses from agents
- [ ] Email signature parsing
- [ ] Customer satisfaction surveys via email
- [ ] Email bounce handling
- [ ] Multiple mailbox support
- [ ] Email categorization using AI/ML

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Run diagnostics: `php artisan email:process --once -vvv`
3. Review this guide's troubleshooting section
4. Contact system administrator

---

**Last Updated:** 2025-12-29
**Version:** 1.0.0
