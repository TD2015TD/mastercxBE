# Email Ticket Numbering System

## Overview

Email tickets use the **TicketNumberSequence** system with a dedicated 'email' sequence configuration that provides consistent, traceable ticket numbers.

## Email Ticket Number Format

### Default Configuration

```
Sequence Name: email
Prefix:        EM
Pattern:       {PREFIX}{YY}{MM}{DD}-{SEQUENCE}
Padding:       4 digits
Reset:         Daily
Example:       EM251229-0001
```

### Breakdown

- `EM` - Email channel prefix
- `25` - Year (2025)
- `12` - Month (December)
- `29` - Day (29th)
- `0001` - Sequential number (resets daily)

## How It Works

### 1. Email Received
```
From: customer@example.com
Subject: Need help with my account
```

### 2. Ticket Number Generated
```php
// ProcessIncomingEmail.php line 265
$ticketNumber = $this->generateTicketNumber('email');

// Calls TicketNumberSequence::generateNextNumber('email')
// Returns: EM251229-0001
```

### 3. Ticket Created
```
Ticket Number: EM251229-0001
Subject:       Need help with my account
Channel:       email
Source:        IMAP
```

### 4. Customer Reply
When customer replies with subject:
```
RE: [EM251229-0001] Need help with my account
```

The system:
1. Extracts ticket number from subject
2. Finds existing ticket
3. Updates instead of creating duplicate

## Sequence Configuration

### Database Table: `ticket_number_sequences`

```sql
SELECT * FROM ticket_number_sequences WHERE name = 'email';
```

| Field | Value |
|-------|-------|
| name | email |
| prefix | EM |
| pattern | {PREFIX}{YY}{MM}{DD}-{SEQUENCE} |
| current_number | (increments) |
| padding | 4 |
| reset_daily | true |
| reset_monthly | false |
| reset_yearly | false |
| is_active | true |

### Seeder

The email sequence is created by `TicketNumberSequenceSeeder`:

```bash
php artisan db:seed --class=TicketNumberSequenceSeeder
```

This creates sequences for all channels:
- **default** → `TKT20251229-0001`
- **phone** → `PH251229-0001`
- **email** → `EM251229-0001` ← Used for email tickets
- **chat** → `CH251229-0001`
- **web** → `WEB251229-0001`
- **walkIn** → `WI251229-0001`
- **social_media** → `SM251229-0001`

## Thread Detection

### Email Reply Detection

The system detects email replies using:

1. **Ticket Number in Subject**
   ```
   Subject: RE: Support Request [EM251229-0001]
   → Extracts: EM251229-0001
   → Links to existing ticket
   ```

2. **Email Headers**
   ```
   In-Reply-To: <message-id>
   References: <original-message-id>
   → Finds conversation thread
   → Links to existing ticket
   ```

### Deduplication

```php
// EmailMessage.php line 75-86
public static function extractTicketNumber(?string $subject): ?string
{
    if (!$subject) {
        return null;
    }

    // Pattern matches: [EM251229-0001] or #EM251229-0001
    if (preg_match('/\[?(EM[0-9\-]+)\]?/i', $subject, $matches)) {
        return strtoupper($matches[1]);
    }

    return null;
}
```

## Customization

### Change Email Ticket Prefix

To change from `EM` to something else (e.g., `EMAIL`):

```sql
UPDATE ticket_number_sequences
SET prefix = 'EMAIL',
    pattern = '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}'
WHERE name = 'email';
```

Result: `EMAIL251229-0001`

### Change Reset Frequency

To reset monthly instead of daily:

```sql
UPDATE ticket_number_sequences
SET reset_daily = false,
    reset_monthly = true
WHERE name = 'email';
```

### Change Padding

To use 5 digits instead of 4:

```sql
UPDATE ticket_number_sequences
SET padding = 5
WHERE name = 'email';
```

Result: `EM251229-00001`

### Custom Pattern

To use year-month only:

```sql
UPDATE ticket_number_sequences
SET pattern = '{PREFIX}{YYYY}{MM}-{SEQUENCE}',
    reset_monthly = true,
    reset_daily = false
WHERE name = 'email';
```

Result: `EM202512-0001` (resets monthly)

## Available Pattern Variables

| Variable | Description | Example |
|----------|-------------|---------|
| {PREFIX} | Sequence prefix | EM |
| {YYYY} | 4-digit year | 2025 |
| {YY} | 2-digit year | 25 |
| {MM} | 2-digit month | 12 |
| {M} | Month no leading zero | 8 |
| {DD} | 2-digit day | 29 |
| {D} | Day no leading zero | 9 |
| {SEQUENCE} | Padded number | 0001 |

## Integration with Email Configurations

Email tickets can be routed by department using `EmailConfiguration`:

```json
{
  "name": "Support Email",
  "imap_username": "support@company.com",
  "department": "Support"
}
```

All tickets from this email will:
- Use sequence: `email`
- Format: `EM251229-XXXX`
- Route to: Support department

## Troubleshooting

### Issue: Ticket numbers not generating

**Check sequence exists:**
```sql
SELECT * FROM ticket_number_sequences WHERE name = 'email' AND is_active = true;
```

**If not found, run seeder:**
```bash
php artisan db:seed --class=TicketNumberSequenceSeeder
```

### Issue: Sequence not resetting daily

**Check last reset date:**
```sql
SELECT name, last_reset_date, current_number, reset_daily
FROM ticket_number_sequences
WHERE name = 'email';
```

**Manual reset:**
```sql
UPDATE ticket_number_sequences
SET current_number = 0,
    last_reset_date = CURRENT_DATE
WHERE name = 'email';
```

### Issue: Duplicate ticket numbers

This should never happen due to database row locking in `TicketNumberSequence::generateNextNumber()`. If it occurs:

1. Check for race conditions
2. Verify database transaction isolation
3. Check logs for sequence generation errors

### Issue: Fallback ticket numbers (TKT format)

If you see tickets like `TKT20251229ABCDEF`, it means:
- Email sequence not found
- Sequence generation failed
- Fallback to random generation

**Fix:**
```bash
php artisan db:seed --class=TicketNumberSequenceSeeder
```

## Code References

### Ticket Number Generation

**ProcessIncomingEmail Command:**
- [app/Console/Commands/ProcessIncomingEmail.php:265](app/Console/Commands/ProcessIncomingEmail.php#L265) - Calls generateTicketNumber('email')
- [app/Console/Commands/ProcessIncomingEmail.php:403-418](app/Console/Commands/ProcessIncomingEmail.php#L403-L418) - generateTicketNumber method

**TicketNumberSequence Model:**
- [app/Models/TicketNumberSequence.php:40-90](app/Models/TicketNumberSequence.php#L40-L90) - generateNextNumber()
- [app/Models/TicketNumberSequence.php:100-120](app/Models/TicketNumberSequence.php#L100-L120) - formatTicketNumber()

**Seeder:**
- [database/seeders/TicketNumberSequenceSeeder.php:51-64](database/seeders/TicketNumberSequenceSeeder.php#L51-L64) - Email sequence config

### Thread Detection

**EmailMessage Model:**
- [app/Models/EmailMessage.php:75-86](app/Models/EmailMessage.php#L75-L86) - extractTicketNumber()
- [app/Models/EmailMessage.php:88-101](app/Models/EmailMessage.php#L88-L101) - generateThreadId()

**ProcessIncomingEmail:**
- [app/Console/Commands/ProcessIncomingEmail.php:158-175](app/Console/Commands/ProcessIncomingEmail.php#L158-L175) - Thread detection logic

## Best Practices

1. **Never manually set ticket numbers** - Always use TicketNumberSequence
2. **Include ticket number in reply emails** - Enables thread detection
3. **Test sequence reset** - Verify daily/monthly/yearly resets work
4. **Monitor sequence gaps** - Large gaps may indicate issues
5. **Backup before changing patterns** - Pattern changes affect all future tickets

## API Management

Email sequences are managed same as other sequences via TicketNumberSequenceController:

```bash
# View email sequence
GET /api/v1/ticket-sequences?name=email

# Update email sequence
PUT /api/v1/ticket-sequences/{id}

# Preview next number
GET /api/v1/ticket-sequences/{id}/preview

# Reset sequence
POST /api/v1/ticket-sequences/{id}/reset
```

## Summary

✅ Email tickets use dedicated `email` sequence
✅ Format: `EM251229-0001` (customizable)
✅ Thread detection via ticket number extraction
✅ Daily reset (configurable)
✅ Database-driven, no code changes needed
✅ Integrated with EmailConfiguration system
✅ Fallback to random if sequence fails

---

**Last Updated:** 2025-12-29
**System Version:** 1.0.0
