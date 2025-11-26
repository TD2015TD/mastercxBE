# Ticket API Documentation

## Overview
The Ticket API provides comprehensive ticket management with SLA tracking, priority-based workflows, and automatic agent assignment for the JKM CRM system.

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

## SLA Configuration

Tickets have automatic SLA (Service Level Agreement) calculation based on priority:

| Priority | SLA Duration | Description |
|----------|--------------|-------------|
| P1 | 60 minutes | Critical - Immediate attention required |
| P2 | 240 minutes (4 hours) | High - Urgent issues affecting operations |
| P3 | 1440 minutes (24 hours) | Medium - Standard business requests |
| P4 | 4320 minutes (3 days) | Low - General inquiries and information |

---

## Endpoints

### 1. List Tickets
Get a paginated list of tickets with extensive filtering options.

**Endpoint:** `GET /tickets`

**Query Parameters:**
- `priority` (optional): Filter by priority (`P1`, `P2`, `P3`, `P4`)
- `status` (optional): Filter by status (`Open`, `In Progress`, `Pending`, `On Hold`, `Resolved`, `Closed`, `Cancelled`)
- `department` (optional): Filter by department
- `assigned_to` (optional): Filter by assigned agent ID
- `customer_id` (optional): Filter by customer ID
- `sla_breached` (optional): Filter SLA breached tickets (`true` or `false`)
- `category` (optional): Filter by category
- `channel` (optional): Filter by channel (`phone`, `email`, `chat`, `web`, `walk-in`)
- `search` (optional): Search by ticket number, subject, or description
- `sort_by` (optional): Field to sort by (default: `created_at`)
- `sort_order` (optional): Sort order (`asc` or `desc`, default: `desc`)
- `per_page` (optional): Results per page (default: 15)

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/tickets?priority=P1&status=Open&sla_breached=true" \
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
        "ticket_number": "TKT20251122001",
        "customer_id": 1,
        "assigned_to": 1,
        "priority": "P1",
        "department": "Customs Operations",
        "category": "Import Clearance",
        "subject": "Urgent: Medical Equipment Clearance",
        "status": "In Progress",
        "sla_duration_minutes": 60,
        "sla_due_at": "2025-11-22T11:30:00.000000Z",
        "sla_breached": false,
        "sla_remaining_minutes": 15,
        "formatted_sla_remaining": "15 minutes remaining",
        "is_overdue": false,
        "channel": "phone",
        "created_at": "2025-11-22T10:30:00.000000Z",
        "customer": {
          "id": 1,
          "name": "Ahmad Faiz bin Abdullah",
          "email": "ahmad.faiz@email.com"
        },
        "assignedAgent": {
          "id": 1,
          "agent_code": "AGT20251122001",
          "name": "Ahmad Rahman"
        }
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

---

### 2. Create Ticket
Create a new ticket with automatic SLA calculation.

**Endpoint:** `POST /tickets`

**Request Body:**
```json
{
  "customer_id": 1,
  "assigned_to": 1,
  "priority": "P2",
  "department": "Customs Operations",
  "category": "Import Clearance",
  "sub_category": "Documentation",
  "subject": "Missing Import Permit",
  "description": "Customer needs assistance with missing import permit for shipment XYZ123",
  "urgency": "High",
  "impact": "Medium",
  "channel": "email",
  "tags": ["import", "documentation", "urgent"],
  "custom_fields": {
    "shipment_number": "XYZ123",
    "cargo_value": "50000"
  }
}
```

**Validation Rules:**
- `customer_id`: Required, must exist in customers table
- `assigned_to`: Optional, must exist in agents table (auto-assigned if not provided)
- `priority`: Required, must be one of: `P1`, `P2`, `P3`, `P4`
- `department`: Required, string, max 100 characters
- `category`: Required, string, max 100 characters
- `sub_category`: Optional, string, max 100 characters
- `subject`: Required, string, max 255 characters
- `description`: Required, string
- `urgency`: Optional, must be one of: `Low`, `Medium`, `High`, `Critical`
- `impact`: Optional, must be one of: `Low`, `Medium`, `High`, `Critical`
- `channel`: Required, must be one of: `phone`, `email`, `chat`, `web`, `walk-in`
- `source`: Optional, string, max 100 characters
- `tags`: Optional, array
- `custom_fields`: Optional, object

**Example Response:**
```json
{
  "success": true,
  "message": "Ticket created successfully",
  "data": {
    "id": 11,
    "ticket_number": "TKT202511220A1B2C",
    "customer_id": 1,
    "assigned_to": 1,
    "priority": "P2",
    "sla_duration_minutes": 240,
    "sla_due_at": "2025-11-22T14:42:00.000000Z",
    "status": "Open",
    "created_at": "2025-11-22T10:42:00.000000Z"
  }
}
```

---

### 3. Get Ticket Details
Retrieve detailed information about a specific ticket.

**Endpoint:** `GET /tickets/{id}`

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "ticket_number": "TKT20251122001",
    "customer_id": 1,
    "assigned_to": 1,
    "created_by": 1,
    "priority": "P1",
    "department": "Customs Operations",
    "category": "Import Clearance",
    "sub_category": "Urgent Clearance",
    "subject": "Urgent: Medical Equipment Clearance",
    "description": "Medical equipment stuck at customs...",
    "urgency": "Critical",
    "impact": "High",
    "status": "In Progress",
    "sla_duration_minutes": 60,
    "sla_due_at": "2025-11-22T11:30:00.000000Z",
    "sla_breached": false,
    "sla_remaining_minutes": 15,
    "formatted_sla_remaining": "15 minutes remaining",
    "is_overdue": false,
    "first_response_at": "2025-11-22T10:32:00.000000Z",
    "first_response_time_minutes": 2,
    "channel": "phone",
    "customer": {
      "id": 1,
      "name": "Ahmad Faiz bin Abdullah",
      "email": "ahmad.faiz@email.com",
      "phone_number": "+60123456789"
    },
    "assignedAgent": {
      "id": 1,
      "agent_code": "AGT20251122001",
      "name": "Ahmad Rahman",
      "department": "Customer Service"
    },
    "creator": {
      "id": 1,
      "agent_code": "AGT20251122001",
      "name": "Ahmad Rahman"
    }
  }
}
```

---

### 4. Update Ticket
Update ticket details.

**Endpoint:** `PUT /tickets/{id}`

**Request Body:** (all fields optional)
```json
{
  "priority": "P1",
  "status": "In Progress",
  "subject": "Updated: Medical Equipment Clearance - URGENT",
  "tags": ["urgent", "medical", "escalated"]
}
```

**Note:** When priority is changed, SLA is automatically recalculated.

---

### 5. Assign Ticket to Agent
Assign or reassign a ticket to an agent.

**Endpoint:** `PUT /tickets/{id}/assign`

**Request Body:**
```json
{
  "agent_id": 2
}
```

**Example Response:**
```json
{
  "success": true,
  "message": "Ticket assigned successfully",
  "data": {
    "id": 1,
    "ticket_number": "TKT20251122001",
    "assigned_to": 2,
    "status": "In Progress",
    "assignedAgent": {
      "id": 2,
      "agent_code": "AGT20251122002",
      "name": "Siti Nurhaliza"
    }
  }
}
```

**Note:** When a ticket with status "Open" is assigned, it automatically changes to "In Progress".

---

### 6. Update Ticket Status
Update the status of a ticket.

**Endpoint:** `PUT /tickets/{id}/status`

**Request Body:**
```json
{
  "status": "Pending",
  "notes": "Waiting for customer to provide additional documents"
}
```

**Valid Status Values:**
- `Open`: Newly created ticket
- `In Progress`: Agent is actively working on it
- `Pending`: Waiting for customer response
- `On Hold`: Temporarily paused
- `Resolved`: Issue fixed, awaiting confirmation
- `Closed`: Completed and confirmed
- `Cancelled`: Ticket cancelled

---

### 7. Resolve Ticket
Mark a ticket as resolved.

**Endpoint:** `POST /tickets/{id}/resolve`

**Request Body:**
```json
{
  "resolution_notes": "Import permit issued. Customer notified via email. Cargo released from customs."
}
```

**Example Response:**
```json
{
  "success": true,
  "message": "Ticket resolved successfully",
  "data": {
    "id": 1,
    "ticket_number": "TKT20251122001",
    "status": "Resolved",
    "resolution_notes": "Import permit issued...",
    "resolution_time_minutes": 45
  }
}
```

---

### 8. Close Ticket
Close a resolved ticket.

**Endpoint:** `POST /tickets/{id}/close`

**Example Response:**
```json
{
  "success": true,
  "message": "Ticket closed successfully",
  "data": {
    "id": 1,
    "ticket_number": "TKT20251122001",
    "status": "Closed"
  }
}
```

---

### 9. Reopen Ticket
Reopen a closed or resolved ticket.

**Endpoint:** `POST /tickets/{id}/reopen`

**Example Response:**
```json
{
  "success": true,
  "message": "Ticket reopened successfully",
  "data": {
    "id": 1,
    "ticket_number": "TKT20251122001",
    "status": "Open",
    "reopened_count": 1
  }
}
```

---

### 10. Record First Response
Record the first response time for a ticket.

**Endpoint:** `POST /tickets/{id}/response`

**Example Response:**
```json
{
  "success": true,
  "message": "First response recorded successfully",
  "data": {
    "id": 1,
    "first_response_at": "2025-11-22T10:32:00.000000Z",
    "first_response_time_minutes": 2
  }
}
```

---

### 11. Get Ticket Statistics
Get overall ticket statistics with optional filtering.

**Endpoint:** `GET /tickets/statistics/summary`

**Query Parameters:**
- `start_date` (optional): Filter from date (YYYY-MM-DD)
- `end_date` (optional): Filter to date (YYYY-MM-DD)
- `department` (optional): Filter by department

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/tickets/statistics/summary?start_date=2025-11-01&end_date=2025-11-30" \
  -H "Authorization: Bearer your-jwt-token"
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "total_tickets": 100,
    "open_tickets": 25,
    "resolved_tickets": 60,
    "closed_tickets": 10,
    "sla_breached": 5,
    "sla_compliance_rate": 95.00,
    "by_priority": {
      "P1": 10,
      "P2": 30,
      "P3": 40,
      "P4": 20
    },
    "avg_resolution_time_minutes": 185.50
  }
}
```

---

## Ticket Lifecycle

```
┌────────┐
│  Open  │ ─────────────────────────┐
└────┬───┘                           │
     │                               │
     │ Assign Agent                  │
     ▼                               │
┌──────────────┐                     │
│ In Progress  │                     │
└──────┬───────┘                     │
       │                             │
       │ Need Info                   │
       ▼                             │
   ┌─────────┐                       │
   │ Pending │ ──────┐               │
   └─────────┘       │               │
       │             │               │
       │ Info        │ Cancel        │ Cancel
       │ Received    │               │
       │             │               │
       ▼             ▼               │
┌──────────────┐  ┌───────────┐     │
│ In Progress  │  │ Cancelled │ ────┘
└──────┬───────┘  └───────────┘
       │
       │ Issue Fixed
       ▼
   ┌──────────┐
   │ Resolved │ ───┐
   └─────┬────┘    │
         │         │
         │ Confirm │ Customer
         │         │ Not Happy
         ▼         │
     ┌────────┐    │
     │ Closed │    │
     └────────┘    │
         │         │
         └─────────┘
         Reopen
```

---

## Departments

Common departments in JKM CRM:
- `Customs Operations`: Import/export clearance, cargo handling
- `Tax Policy`: Tax inquiries, duty calculations, refunds
- `Trade Compliance`: Documentation, licenses, permits
- `Enforcement`: Investigations, fraud detection
- `Information Services`: General inquiries, process information
- `IT Operations`: Technical support, system issues

---

## Categories and Sub-Categories

### Customs Operations
- Import Clearance
  - Urgent Clearance
  - Standard Processing
  - Documentation Issue
- Cargo Release
  - Delayed Release
  - Inspection Required
- Inspection
  - Physical Inspection
  - Document Verification

### Tax Policy
- Tax Inquiry
  - SST Calculation
  - Duty Assessment
  - Valuation Query
- Duty Refund
  - Refund Claim
  - Overpayment Correction

### Trade Compliance
- Documentation
  - Certificate Request
  - License Application
- License Application
  - Import License
  - Export License
- Tariff Classification
  - HS Code Query
  - Product Classification

---

## Auto-Assignment Logic

When a ticket is created without an `assigned_to` value, the system automatically assigns it to:

1. An agent in the same department
2. With availability status = "available"
3. With status = "active"
4. Sorted by tickets_resolved_today (ascending) - least busy agent first

---

## SLA Breach Handling

- SLA timer starts when ticket is created
- SLA is calculated based on priority (P1: 60min, P2: 4hrs, P3: 24hrs, P4: 3 days)
- `sla_remaining_minutes` is updated when ticket is retrieved or updated
- `sla_breached` flag is set to `true` when current time exceeds `sla_due_at`
- Resolved, Closed, or Cancelled tickets don't check SLA anymore

---

## Customer Feedback

After ticket resolution, customers can provide:
- `customer_rating`: Integer 1-5
- `customer_feedback`: Text feedback

These metrics are used to calculate customer satisfaction ratings and agent performance.

---

## Best Practices

1. **Priority Selection**: 
   - P1 (Critical): System down, business-stopping issues
   - P2 (High): Significant impact but workarounds exist
   - P3 (Medium): Standard requests, no immediate impact
   - P4 (Low): Information requests, general inquiries

2. **SLA Management**:
   - Monitor `sla_remaining_minutes` to prioritize work
   - Use SLA breach filter to identify at-risk tickets
   - Record first response promptly to track agent performance

3. **Status Updates**:
   - Update status regularly to reflect actual progress
   - Use "Pending" when waiting for customer
   - Use "On Hold" for external dependencies

4. **Resolution Process**:
   - Always provide detailed resolution notes
   - Resolve ticket before closing
   - Only close after customer confirmation

5. **Ticket Search**:
   - Use specific filters to narrow results
   - Search by ticket number for exact matches
   - Use department filters for team-specific views

6. **Custom Fields**:
   - Use for domain-specific data (shipment numbers, values, etc.)
   - Store structured data as JSON
   - Document custom field usage in your team

---

## Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "errors": {
    "customer_id": ["The customer id field is required."],
    "priority": ["The selected priority is invalid."]
  }
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Ticket not found"
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "Failed to create ticket",
  "error": "Database connection failed"
}
```
