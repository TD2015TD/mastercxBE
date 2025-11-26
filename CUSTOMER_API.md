# Customer API Documentation

## Overview
The Customer API provides endpoints for managing customer records in the JKM CRM system. It supports Malaysian ID verification with multiple ID types (MyKad, MyTentera, MyPolis, MyPR, Passport).

## Base URL
```
http://localhost:8000/api/v1
```

## Authentication
All endpoints require JWT authentication. Include the token in the Authorization header:
```
Authorization: Bearer {your-jwt-token}
```

---

## Endpoints

### 1. List Customers
Get a paginated list of customers with optional filtering and search.

**Endpoint:** `GET /customers`

**Query Parameters:**
- `customer_type` (optional): Filter by type (`individual` or `business`)
- `status` (optional): Filter by status (`active`, `inactive`, `blocked`)
- `id_type` (optional): Filter by ID type (`MyKad`, `MyTentera`, `MyPolis`, `MyPR`, `Passport`)
- `search` (optional): Search by name, email, phone, ID number, or company name
- `sort_by` (optional): Field to sort by (default: `created_at`)
- `sort_order` (optional): Sort order (`asc` or `desc`, default: `desc`)
- `per_page` (optional): Results per page (default: 15)
- `with_tickets` (optional): Include tickets relationship

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/customers?status=active&customer_type=business&per_page=10" \
  -H "Authorization: Bearer your-jwt-token"
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Ahmad Faiz bin Abdullah",
        "email": "ahmad.faiz@email.com",
        "phone_number": "+60123456789",
        "id_type": "MyKad",
        "id_no": "850123-14-5678",
        "company_name": null,
        "address": "No. 45, Jalan Tun Razak",
        "city": "Kuala Lumpur",
        "state": "Wilayah Persekutuan",
        "postal_code": "50400",
        "country": "Malaysia",
        "customer_type": "individual",
        "status": "active",
        "total_tickets": 2,
        "resolved_tickets": 1,
        "satisfaction_rating": "4.50",
        "created_at": "2025-11-22T10:30:00.000000Z",
        "updated_at": "2025-11-22T10:30:00.000000Z"
      }
    ],
    "per_page": 10,
    "total": 10
  }
}
```

---

### 2. Create Customer
Create a new customer record.

**Endpoint:** `POST /customers`

**Request Body:**
```json
{
  "name": "Ahmad Faiz bin Abdullah",
  "email": "ahmad.faiz@email.com",
  "phone_number": "+60123456789",
  "id_type": "MyKad",
  "id_no": "850123-14-5678",
  "company_name": null,
  "address": "No. 45, Jalan Tun Razak",
  "city": "Kuala Lumpur",
  "state": "Wilayah Persekutuan",
  "postal_code": "50400",
  "country": "Malaysia",
  "customer_type": "individual",
  "preferred_language": "ms",
  "tags": ["vip", "regular-customer"],
  "notes": "Regular customer since 2020"
}
```

**Validation Rules:**
- `name`: Required, string, max 255 characters
- `email`: Required, valid email, unique
- `phone_number`: Required, string, max 20 characters
- `id_type`: Required, must be one of: `MyKad`, `MyTentera`, `MyPolis`, `MyPR`, `Passport`
- `id_no`: Required, string, unique
- `company_name`: Optional, string, max 255 characters
- `address`: Optional, string
- `city`: Optional, string, max 100 characters
- `state`: Optional, string, max 100 characters
- `postal_code`: Optional, string, max 10 characters
- `country`: Optional, string, max 100 characters
- `customer_type`: Optional, must be `individual` or `business`
- `business_registration_no`: Optional, string, max 50 characters
- `preferred_language`: Optional, must be one of: `en`, `ms`, `zh`, `ta`
- `tags`: Optional, array
- `notes`: Optional, string

**Example Response:**
```json
{
  "success": true,
  "message": "Customer created successfully",
  "data": {
    "id": 11,
    "name": "Ahmad Faiz bin Abdullah",
    "email": "ahmad.faiz@email.com",
    "id_type": "MyKad",
    "id_no": "850123-14-5678",
    "created_at": "2025-11-22T10:42:00.000000Z"
  }
}
```

---

### 3. Get Customer Details
Retrieve detailed information about a specific customer.

**Endpoint:** `GET /customers/{id}`

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/customers/1" \
  -H "Authorization: Bearer your-jwt-token"
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Ahmad Faiz bin Abdullah",
    "email": "ahmad.faiz@email.com",
    "phone_number": "+60123456789",
    "id_type": "MyKad",
    "id_no": "850123-14-5678",
    "formatted_id": "MyKad: 850123-14-5678",
    "company_name": null,
    "customer_type": "individual",
    "status": "active",
    "total_tickets": 5,
    "resolved_tickets": 3,
    "resolution_rate": 60.00,
    "open_tickets_count": 2,
    "satisfaction_rating": "4.50",
    "tickets": [
      {
        "id": 1,
        "ticket_number": "TKT20251122001",
        "subject": "Import Clearance Issue",
        "status": "In Progress",
        "priority": "P2",
        "created_at": "2025-11-22T08:30:00.000000Z"
      }
    ]
  }
}
```

---

### 4. Update Customer
Update an existing customer record.

**Endpoint:** `PUT /customers/{id}`

**Request Body:** (all fields optional, only include fields to update)
```json
{
  "status": "inactive",
  "notes": "Account suspended due to payment issues",
  "tags": ["suspended", "payment-issue"]
}
```

**Example Response:**
```json
{
  "success": true,
  "message": "Customer updated successfully",
  "data": {
    "id": 1,
    "name": "Ahmad Faiz bin Abdullah",
    "status": "inactive",
    "updated_at": "2025-11-22T11:00:00.000000Z"
  }
}
```

---

### 5. Delete Customer
Soft delete a customer record.

**Endpoint:** `DELETE /customers/{id}`

**Example Request:**
```bash
curl -X DELETE "http://localhost:8000/api/v1/customers/1" \
  -H "Authorization: Bearer your-jwt-token"
```

**Example Response:**
```json
{
  "success": true,
  "message": "Customer deleted successfully"
}
```

---

### 6. Search Customer
Search for a customer by email or ID number.

**Endpoint:** `POST /customers/search`

**Request Body:**
```json
{
  "email": "ahmad.faiz@email.com"
}
```
or
```json
{
  "id_no": "850123-14-5678"
}
```

**Example Response (Found):**
```json
{
  "success": true,
  "found": true,
  "data": {
    "id": 1,
    "name": "Ahmad Faiz bin Abdullah",
    "email": "ahmad.faiz@email.com",
    "id_no": "850123-14-5678"
  }
}
```

**Example Response (Not Found):**
```json
{
  "success": true,
  "found": false,
  "data": null,
  "message": "Customer not found"
}
```

---

### 7. Get Customer Statistics
Retrieve statistics for a specific customer.

**Endpoint:** `GET /customers/{id}/statistics`

**Example Response:**
```json
{
  "success": true,
  "data": {
    "total_tickets": 10,
    "resolved_tickets": 7,
    "open_tickets": 3,
    "resolution_rate": 70.00,
    "satisfaction_rating": "4.50",
    "last_contact_at": "2025-11-22T09:30:00.000000Z"
  }
}
```

---

## ID Types

The system supports the following Malaysian ID types:

1. **MyKad**: National Identity Card (for Malaysian citizens)
   - Format: YYMMDD-PB-NNNN
   - Example: 850123-14-5678

2. **MyTentera**: Military Identity Card
   - Format: MTxxxxxx
   - Example: MT123456

3. **MyPolis**: Police Identity Card
   - Format: MPxxxxxx
   - Example: MP234567

4. **MyPR**: Permanent Resident Identity Card
   - Format: PRxxxxxx
   - Example: PR345678

5. **Passport**: International Passport
   - Format: Varies by country
   - Example: A12345678

---

## Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "errors": {
    "email": ["The email has already been taken."],
    "id_no": ["The id no has already been taken."]
  }
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Customer not found"
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "Failed to create customer",
  "error": "Database connection failed"
}
```

---

## Customer Types

### Individual Customers
- Personal customers
- Fields: name, email, phone, ID type, ID number
- Optional: address, preferences

### Business Customers
- Company/organization customers
- Required additional fields:
  - `company_name`
  - `business_registration_no`
- Useful for tracking corporate accounts

---

## Best Practices

1. **ID Verification**: Always validate ID numbers against the appropriate format for the selected ID type
2. **Unique Constraints**: Email and ID number must be unique across all customers
3. **Search Performance**: Use the search endpoint for quick lookups instead of filtering the list
4. **Customer Status**: Use status changes to track customer lifecycle (active, inactive, blocked)
5. **Tags**: Use tags for flexible categorization (e.g., vip, corporate, importer, exporter)
6. **Metrics**: Customer metrics (total_tickets, resolved_tickets) are automatically updated when tickets change
