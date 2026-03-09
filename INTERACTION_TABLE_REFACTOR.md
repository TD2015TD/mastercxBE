# Customer Interaction Table Refactor

## Overview
This refactor moves the `phone_number` field from the `customers` table to the `customer_interactions` table. This allows tracking phone numbers for each specific interaction, as different interactions with the same customer may involve different contact numbers.

Additionally, interaction types are now pulled from the `lookups` table via the `interaction_type_id` foreign key, providing better data normalization and multilingual support (English and Malay).

## Changes Made

### 1. Database Migrations

#### Migration: `2026_02_14_100530_add_phone_number_to_customer_interactions_table.php`
**Purpose**: Add new columns to `customer_interactions` table

**Changes**:
- Added `phone_number` (string, max 20) - nullable
- Added `interaction_type_id` (foreign key to `lookups`) - nullable with cascade delete to null

```php
$table->string('phone_number', 20)->nullable()->after('channel');
$table->foreignId('interaction_type_id')->nullable()->after('interaction_type')->constrained('lookups')->onDelete('set null');
```

#### Migration: `2026_02_14_101217_remove_phone_number_from_customers_table.php`
**Purpose**: Remove phone_number from customers table

**Changes**:
- Dropped `phone_number` column from `customers` table
- Rollback restores the column with same structure

### 2. Model Updates

#### `Customer.php`
**Changes**:
- Removed `phone_number` from the `$fillable` array
- Phone number is no longer a customer property; it's tracked per interaction

#### `CustomerInteraction.php`
**Changes**:
- Added `phone_number` to `$fillable` array
- Added `interaction_type_id` to `$fillable` array
- Added new relationship method `interactionTypeLookup()`:
  ```php
  public function interactionTypeLookup()
  {
      return $this->belongsTo(Lookup::class, 'interaction_type_id');
  }
  ```

### 3. Controller Updates

#### `CustomerInteractionController.php`

**Index method**:
- Updated with clause to include `interactionTypeLookup` relationship

**Store method**:
- Added validation for `interaction_type_id` (must exist in lookups table)
- Added validation for `phone_number` (string, max 20)
- Updated response to load `interactionTypeLookup` relationship

**Show method**:
- Updated to load `interactionTypeLookup` relationship

**Update method**:
- Added validation for `interaction_type_id` and `phone_number`
- Updated to reload `interactionTypeLookup` relationship

**customerTimeline method**:
- Updated to load `interactionTypeLookup` relationship

### 4. Lookup Data

Interaction types are already seeded in the `lookups` table with the following entries:
- `call` - Phone Call / Panggilan Telefon
- `email` - Email / E-mel
- `chat` - Chat / Sembang
- `sms` - SMS / SMS
- `meeting` - Meeting / Mesyuarat
- `note` - Note / Nota

Each entry includes:
- Bilingual names (English and Malay)
- Icons for UI display
- Sort order for dropdown displays

## API Changes

### Request Format

**Creating an Interaction**:
```json
{
  "customer_id": 1,
  "ticket_id": 1,
  "interaction_type": "call",
  "interaction_type_id": 5,
  "channel": "phone",
  "phone_number": "+60123456789",
  "subject": "Customer support call",
  "description": "Discussed billing issue",
  "duration_minutes": 15,
  "sentiment": "positive",
  "notes": "Customer satisfied with resolution"
}
```

### Response Format

**Interaction Response**:
```json
{
  "id": 1,
  "customer_id": 1,
  "interaction_type": "call",
  "interaction_type_id": 5,
  "channel": "phone",
  "phone_number": "+60123456789",
  "subject": "Customer support call",
  "description": "Discussed billing issue",
  "duration_minutes": 15,
  "sentiment": "positive",
  "interactionTypeLookup": {
    "id": 5,
    "type": "interaction_type",
    "code": "call",
    "name_en": "Phone Call",
    "name_ms": "Panggilan Telefon",
    "icon": "phone",
    "sort_order": 1
  },
  "created_at": "2026-02-14T10:05:30Z",
  "updated_at": "2026-02-14T10:05:30Z"
}
```

## Migration Steps

1. **Backup Database**: Always backup your database before running migrations
   ```bash
   # Example: mysqldump command or your database backup tool
   ```

2. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

3. **Verify Data**:
   - Check that `customer_interactions` table now has `phone_number` and `interaction_type_id` columns
   - Confirm `customers` table no longer has `phone_number` column
   - Verify interaction types in `lookups` table

## Rollback Steps

If you need to revert these changes:

```bash
php artisan migrate:rollback --step=2
```

This will reverse both migrations in reverse order:
1. Remove `phone_number` and `interaction_type_id` from `customer_interactions`
2. Restore `phone_number` to `customers`

## Frontend Integration

### Frontend Service Update Required

The `customerInteractionService.ts` in the frontend needs to be updated to:
1. Accept `phone_number` in create/update requests
2. Handle `interaction_type_id` from the lookup table
3. Display interaction types from the lookup with bilingual labels

### Example Frontend Changes

```typescript
interface CustomerInteraction {
  id: number;
  customer_id: number;
  interaction_type: string;
  interaction_type_id: number; // New field
  channel: string;
  phone_number: string; // New field
  subject: string;
  // ... other fields
  interactionTypeLookup?: Lookup; // New relationship
}
```

## Validation Rules

### Create Interaction
- `customer_id`: nullable, must exist in customers table
- `interaction_type`: required, one of: [call, email, chat, sms, meeting, note]
- `interaction_type_id`: nullable, must exist in lookups table
- `channel`: required, one of: [phone, email, chat, web, walk-in, social_media]
- `phone_number`: nullable, string, max 20 characters
- `subject`: required, string, max 500 characters
- `description`: nullable, string
- `duration_minutes`: nullable, integer ≥ 0
- `sentiment`: nullable, one of: [positive, neutral, negative]
- `notes`: nullable, string
- `metadata`: nullable, array
- `interaction_at`: nullable, must be a valid date

## Benefits of This Refactor

1. **Better Data Normalization**: Phone numbers are now contextual to each interaction
2. **Flexibility**: Different interactions with the same customer can have different contact numbers
3. **Referential Integrity**: Interaction types are managed through the lookups table
4. **Multilingual Support**: Interaction types now have bilingual labels
5. **Consistency**: Lookup values are centralized and can be managed through the lookup admin interface
6. **Extensibility**: Easy to add more metadata or properties to lookups without database changes

## Database Schema

### customer_interactions table (updated)
```sql
CREATE TABLE customer_interactions (
  id BIGINT UNSIGNED PRIMARY KEY,
  customer_id BIGINT UNSIGNED NULLABLE,
  ticket_id BIGINT UNSIGNED NULLABLE,
  agent_id BIGINT UNSIGNED NULLABLE,
  conversation_id VARCHAR(100) NULLABLE,
  interaction_type VARCHAR(255) NOT NULL,
  interaction_type_id BIGINT UNSIGNED NULLABLE,
  channel VARCHAR(255) NOT NULL,
  phone_number VARCHAR(20) NULLABLE,
  subject VARCHAR(500) NOT NULL,
  description TEXT NULLABLE,
  duration_minutes INT NULLABLE,
  sentiment ENUM('positive', 'neutral', 'negative') NULLABLE,
  notes TEXT NULLABLE,
  metadata JSON NULLABLE,
  interaction_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  
  CONSTRAINT fk_customer_id FOREIGN KEY (customer_id) 
    REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_id FOREIGN KEY (ticket_id) 
    REFERENCES tickets(id) ON DELETE SET NULL,
  CONSTRAINT fk_agent_id FOREIGN KEY (agent_id) 
    REFERENCES agents(id) ON DELETE SET NULL,
  CONSTRAINT fk_interaction_type_id FOREIGN KEY (interaction_type_id) 
    REFERENCES lookups(id) ON DELETE SET NULL,
  
  INDEX idx_customer_interaction_at (customer_id, interaction_at),
  INDEX idx_agent_interaction_at (agent_id, interaction_at),
  INDEX idx_interaction_type (interaction_type)
);
```

### customers table (updated)
```sql
-- phone_number column removed
-- All phone numbers are now in customer_interactions table
```

## Questions & Support

For questions about this refactor:
1. Check the API documentation for updated endpoints
2. Review the model relationships in the code
3. Refer to the lookup table seeding for available interaction types
