# Contact Table Implementation Guide

## Overview
The Contact table has been successfully implemented to support a separate, normalized contact management system. This replaces storing phone numbers directly on the Customer table, allowing customers to have multiple contact types (Phone, Email, Mobile, Fax, Office, WhatsApp) with proper referential integrity.

## Database Schema

### contacts table
```sql
CREATE TABLE contacts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    customer_id BIGINT FOREIGN KEY (CASCADE DELETE),
    contact_type_id BIGINT FOREIGN KEY (RESTRICT DELETE),
    value VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT false,
    notes TEXT NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULLABLE,
    
    -- Constraints
    UNIQUE(customer_id, contact_type_id, value),
    INDEX(customer_id, contact_type_id),
    INDEX(is_primary)
)
```

### Contact Types (lookups table)
The following contact types have been configured:
- `phone`: Standard phone number (Telefon)
- `email`: Email address (E-mel)
- `mobile`: Mobile/cell phone (Mudah Alih)
- `fax`: Fax number (Faks)
- `office`: Office number (Pejabat)
- `whatsapp`: WhatsApp number (WhatsApp)

Each type includes:
- Bilingual names (name_en, name_ms)
- Icons for UI display
- Color codes for visual identification

## Backend Implementation

### Models

#### Contact Model (`app/Models/Contact.php`)
```php
class Contact extends Model {
    use SoftDeletes;
    
    // Relationships
    - belongsTo(Customer)
    - belongsTo(Lookup as contactType)
    
    // Scopes
    - primary()          // Get primary contacts
    - byType(typeId)     // Filter by contact type
    - byValue(value)     // Search by value
    - byCustomer(custId) // Filter by customer
    
    // Attributes
    - is_primary: boolean
    - contact_type_id: FK to lookups
    - value: string (phone, email, etc.)
    - notes: nullable string
}
```

#### Customer Model Updates (`app/Models/Customer.php`)
```php
public function contacts(): HasMany {
    return $this->hasMany(Contact::class);
}
```

### API Controller (`app/Http/Controllers/Api/ContactController.php`)

**Methods:**

1. **index()** - List all contacts with filtering
   - Query params: customer_id, contact_type_id, is_primary, search, per_page, page
   - Returns: Paginated list of contacts with relationships

2. **store(Request $request)** - Create new contact
   - Required: customer_id, contact_type_id, value
   - Optional: is_primary, notes
   - Handles: Auto-unmarking other primary contacts when setting new primary

3. **show($id)** - Get single contact details
   - Returns: Contact with related customer and contact type lookup

4. **update(Request $request, $id)** - Update existing contact
   - Mutable fields: contact_type_id, value, is_primary, notes
   - Handles: Primary contact logic

5. **destroy($id)** - Delete contact
   - Soft delete: Preserves data with deleted_at timestamp

6. **findByPhone($phoneNumber)** - Find customer by phone (for incoming calls)
   - Route: `GET /contacts/find-by-phone/{phoneNumber}`
   - Behavior:
     - Normalizes phone (removes non-digits)
     - Searches both phone and mobile contact types
     - Returns: Contact + Customer data for auto-fill
     - Error handling: Graceful 404 if not found
   - Use case: Incoming call handler in TelephonyBar

### API Routes (`routes/api.php`)
```php
Route::apiResource('contacts', ContactController::class);
Route::get('/contacts/find-by-phone/{phoneNumber}', [ContactController::class, 'findByPhone']);
Route::get('/customers/{customerId}/contacts', [ContactController::class, 'index']);
```

## Frontend Implementation

### ContactService (`src/services/contactService.ts`)

**Interfaces:**
- `Contact` - Contact data model with types
- `ContactCreateUpdate` - Request payload for create/update
- `ContactResponse<T>` - Generic API response wrapper
- `ContactPaginatedResponse` - Paginated contacts response
- `Lookup` - Contact type lookup reference

**Methods:**
- `getContacts(params)` - Get all contacts with filters
- `getCustomerContacts(customerId)` - Get contacts for specific customer
- `getContact(id)` - Get single contact
- `createContact(data)` - Create new contact
- `updateContact(id, data)` - Update contact
- `deleteContact(id)` - Delete contact
- `findByPhone(phoneNumber)` - Find customer by phone (incoming calls)
- `searchByValue(value, contactTypeId)` - Search contacts by value
- `getPrimaryContacts(customerId)` - Get primary contact per type

### TelephonyBar Updates (`src/components/TelephonyBar.jsx`)

**Changes:**
1. Imported `contactService`
2. Replaced `findCustomerByPhoneFromInteractions()` with new implementation
3. New function now:
   - Calls `contactService.findByPhone(phoneNumber)`
   - Searches Contact table instead of interactions
   - Returns customer info for auto-fill on incoming calls
   - Gracefully handles 404 if contact not found

**Call Flow:**
```
Incoming Call (phone number)
  ↓
TelephonyBar receives call event
  ↓
findCustomerByPhoneFromInteractions(callerNumber)
  ↓
contactService.findByPhone(callerNumber)
  ↓
ContactController.findByPhone()
  ↓
Normalize phone → Search contacts table (phone, mobile types)
  ↓
Return contact + customer if found
  ↓
Pre-fill ticket creation form with customer info
  ↓
Show toast: "Customer [Name] identified from previous call"
```

## Migration Path

### Data Migration Strategy
If migrating from phone numbers stored on customers:

1. **New installations**: Skip legacy steps, start with clean Contact table
2. **Existing systems**:
   ```php
   // Note: Manual migration if needed
   // 1. Move existing customer.phone_number to contacts table
   // 2. Create contact records with contact_type_id = 'phone'
   // 3. Set is_primary = true for migrated contacts
   // 4. Drop phone_number from customers table
   ```

### Soft Deletes
- Contacts use soft deletes (`deleted_at` field)
- Deleted contacts remain queryable via `withTrashed()`
- Restore via `restore()` method

## Validation Rules

### Create Contact
```
customer_id: required, exists in customers table
contact_type_id: required, exists in lookups table
value: required, string, max 255
is_primary: nullable, boolean
notes: nullable, string, max 500
```

### Update Contact
```
contact_type_id: exists in lookups table
value: string, max 255
is_primary: nullable, boolean
notes: nullable, string, max 500
```

## Primary Contact Logic

**Automatic Primary Management:**
- When setting `is_primary = true` for a contact:
  - Automatically unmarks other primary contacts of **same type** for same customer
  - Example: Setting second phone as primary automatically unmarks first phone
  - Email contacts independent from phone contacts

**Why?**
- Ensures only one contact per type is marked as primary
- Prevents confusion in UI/UX
- Maintains data integrity at application level

## Phone Number Normalization

**Algorithm (for incoming calls):**
```
Input: +60-12-1234567, 012-1234567, 6012-1234567
↓
Normalize: Remove all non-digits
↓
Result: 60121234567 (for comparison)
↓
Search: Find contact with normalized value matching normalized input
```

**Benefits:**
- Handles various phone formats: +60, 0, 60 prefixes
- With/without separators (-, space)
- Exact match via normalization

## Usage Examples

### 1. Create Contact for Customer
```typescript
const response = await contactService.createContact({
    customer_id: 123,
    contact_type_id: 2,  // phone type
    value: '+60-12-1234567',
    is_primary: true,
    notes: 'Mobile phone'
});
```

### 2. Find Customer by Incoming Call
```typescript
try {
    const response = await contactService.findByPhone('+60121234567');
    if (response.success) {
        const { contact, customer } = response.data;
        // Pre-fill form with customer info
        setSelectedCustomer(customer);
    }
} catch (err) {
    // No customer found - new caller
    console.log('New caller - no history');
}
```

### 3. Get All Contacts for Customer
```typescript
const response = await contactService.getCustomerContacts(customerId);
const phoneContacts = response.data.filter(c => c.contactType.code === 'phone');
```

### 4. Update Primary Contact
```typescript
await contactService.updateContact(contactId, {
    is_primary: true
});
// Other primary contacts of same type auto-unmarked
```

## Error Handling

**Frontend:**
- `contactService.findByPhone()` returns null on error
- TelephonyBar gracefully falls back to unknown caller flow
- Toast notifications inform user of results

**Backend:**
- Validation returns 422 with field errors
- 404 on contact not found (findByPhone)
- 500 on server errors with error message

## Performance Considerations

1. **Indexes:**
   - `(customer_id, contact_type_id)` for filtering
   - `is_primary` for primary contact queries
   - Unique constraint on `(customer_id, contact_type_id, value)`

2. **Soft Deletes:**
   - Queries automatically exclude deleted records
   - Use `withTrashed()` to include deleted contacts

3. **Pagination:**
   - Default 20 per page in index()
   - Configurable via per_page parameter

## UI Components (Frontend - Pending Implementation)

- **ContactManager Component**: CRUD interface for managing contacts
- **ContactList Component**: Display customer contacts
- **ContactForm Component**: Create/edit contact form
- **ContactTypeSelector**: Dropdown for contact types with icons

## Related Systems

### Interactions Table
- No longer stores phone numbers ~~(phone_number removed)~~
- Stores interaction_type_id (lookup reference)
- Phone stored in Contact table instead

### Lookups Table
- contact_type entries with bilingual support
- Each type has icon, color, sort_order for UI

### Customer Table
- Removed phone_number field
- Added contacts() relationship

### TelephonyBar
- Uses Contact table for incoming call lookup
- Searches by phone number normalization
- Pre-fills customer in ticket creation

## Testing Checklist

- [ ] Create contact for customer
- [ ] Update contact details
- [ ] Mark contact as primary (verify unmarking of other primary)
- [ ] Search contact by value
- [ ] Find customer by phone (incoming call)
- [ ] Phone normalization (various formats)
- [ ] Soft delete contact
- [ ] Restore deleted contact
- [ ] Pagination of contacts
- [ ] Foreign key constraints
- [ ] Unique constraint (no duplicate contact per type)

## Migration Status

**Completed:**
✅ Contact table migration created
✅ Contact model with relationships
✅ ContactController with full CRUD
✅ ContactService (frontend)
✅ TelephonyBar integration
✅ Route registration
✅ Contact type lookups seeded

**Pending:**
⏳ Run migrations: `php artisan migrate`
⏳ Frontend Contact management UI components
⏳ End-to-end testing
⏳ Update agent/customer documentation

## Next Steps

1. **Run Migrations:**
   ```bash
   php artisan migrate
   ```

2. **Create Frontend Contact Components** (Optional):
   ```
   - ContactManager (main CRUD interface)
   - ContactForm (create/edit)
   - ContactList (display contacts)
   ```

3. **Test:**
   - Create test customer with multiple contacts
   - Simulate incoming call with phone matching
   - Verify auto-fill in ticket creation

4. **Documentation:**
   - Update agent training materials
   - Document contact management workflow
   - Add to customer 360 profile

## FAQ

**Q: Can a customer have multiple contacts of same type?**
A: Yes, but only one can be marked `is_primary = true` per type.

**Q: What happens if I delete a customer?**
A: All contacts are cascade-deleted with the customer (CASCADE constraint).

**Q: What if I delete a contact type lookup?**
A: Contacts cannot be deleted (RESTRICT constraint) to prevent orphaned records.

**Q: How does phone normalization work?**
A: Removes all non-digits for comparison: `+60-12-1234567` → `60121234567`

**Q: Is soft delete mandatory?**
A: Yes, contacts use soft deletes for data retention. Use `forceDelete()` to permanently remove.

**Q: Can I search contacts across all customers?**
A: Yes, use `contactService.getContacts()` without customer_id parameter.

---

**Last Updated:** 2026-02-14
**Version:** 1.0.0
**Status:** Ready for Migration
