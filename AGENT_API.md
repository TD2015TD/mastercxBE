# Agent Management API Documentation

## Overview
The Agent Management API provides endpoints for managing agent profiles, including registration, updates, availability tracking, and performance metrics. Agents are associated with users and contain Mitel integration data.

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

### 1. List All Agents
Get a paginated list of agents with optional filtering and search.

**Endpoint:** `GET /agents`

**Query Parameters:**
- `department` (string, optional) - Filter by department
- `status` (string, optional) - Filter by status: `active`, `inactive`, `suspended`
- `availability` (string, optional) - Filter by availability: `available`, `busy`, `away`, `offline`
- `search` (string, optional) - Search by name, email, agent code, or employee ID
- `sort_by` (string, optional) - Sort field (default: `created_at`)
- `sort_order` (string, optional) - Sort order: `asc`, `desc` (default: `desc`)
- `per_page` (integer, optional) - Items per page (default: 15)

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/agents?department=Customer+Service&status=active&per_page=10" \
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
        "user_id": 1,
        "agent_code": "CUS-A1B2C3",
        "employee_id": "EMP-001",
        "department": "Customer Service",
        "designation": "Senior Agent",
        "mitel_agent_id": "MITEL-001",
        "mitel_extension": "1001",
        "mitel_queue_ids": ["Q001", "Q002"],
        "status": "active",
        "availability": "available",
        "skills": ["customs", "gst", "trade"],
        "languages": ["en", "ms"],
        "max_concurrent_tickets": 5,
        "tickets_resolved_today": 12,
        "daily_target": 15,
        "avg_response_time": 8.5,
        "sla_compliance_rate": 94.0,
        "customer_satisfaction": 4.3,
        "shift_start": "08:00:00",
        "shift_end": "17:00:00",
        "working_days": [1, 2, 3, 4, 5],
        "notes": null,
        "last_activity_at": "2024-11-22T09:30:00.000000Z",
        "created_at": "2024-11-22T09:21:00.000000Z",
        "updated_at": "2024-11-22T09:21:00.000000Z",
        "deleted_at": null,
        "user": {
          "id": 1,
          "name": "Ahmad Rahman",
          "email": "ahmad.rahman@jkdm.gov.my",
          "role": "agent",
          "ignite_id": "IGN-001",
          "created_at": "2024-11-22T09:21:00.000000Z"
        }
      }
    ],
    "first_page_url": "http://localhost:8000/api/v1/agents?page=1",
    "last_page": 1,
    "per_page": 10,
    "total": 4
  }
}
```

---

### 2. Create New Agent
Register a new agent profile for an existing user.

**Endpoint:** `POST /agents`

**Request Body:**
```json
{
  "user_id": 5,
  "department": "Customer Service",
  "designation": "Agent",
  "employee_id": "EMP-005",
  "mitel_agent_id": "MITEL-005",
  "mitel_extension": "1005",
  "mitel_queue_ids": ["Q001", "Q002"],
  "skills": ["customs", "gst"],
  "languages": ["en", "ms"],
  "max_concurrent_tickets": 5,
  "daily_target": 15,
  "shift_start": "08:00",
  "shift_end": "17:00",
  "working_days": [1, 2, 3, 4, 5],
  "notes": "New agent on probation"
}
```

**Required Fields:**
- `user_id` - Must exist in users table and not already have an agent profile
- `department` - Agent's department

**Example Request:**
```bash
curl -X POST "http://localhost:8000/api/v1/agents" \
  -H "Authorization: Bearer your-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 5,
    "department": "Customer Service",
    "daily_target": 15,
    "skills": ["customs", "gst"]
  }'
```

**Example Response:**
```json
{
  "success": true,
  "message": "Agent created successfully",
  "data": {
    "id": 5,
    "user_id": 5,
    "agent_code": "CUS-X7Y8Z9",
    "department": "Customer Service",
    "status": "active",
    "availability": "offline",
    "daily_target": 15,
    "created_at": "2024-11-22T10:00:00.000000Z",
    "user": {
      "id": 5,
      "name": "New Agent",
      "email": "new.agent@jkdm.gov.my"
    }
  }
}
```

---

### 3. Get Agent Details
Retrieve detailed information about a specific agent.

**Endpoint:** `GET /agents/{id}`

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/agents/1" \
  -H "Authorization: Bearer your-jwt-token"
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "agent_code": "CUS-A1B2C3",
    "department": "Customer Service",
    "status": "active",
    "availability": "available",
    "tickets_resolved_today": 12,
    "daily_target": 15,
    "sla_compliance_rate": 94.0,
    "user": {
      "id": 1,
      "name": "Ahmad Rahman",
      "email": "ahmad.rahman@jkdm.gov.my",
      "role": "agent"
    }
  }
}
```

---

### 4. Update Agent
Update agent profile information.

**Endpoint:** `PUT /agents/{id}` or `PATCH /agents/{id}`

**Request Body (all fields optional):**
```json
{
  "department": "Trade Compliance",
  "designation": "Senior Agent",
  "status": "active",
  "availability": "busy",
  "skills": ["customs", "gst", "trade"],
  "languages": ["en", "ms", "zh"],
  "max_concurrent_tickets": 8,
  "daily_target": 20,
  "tickets_resolved_today": 15,
  "avg_response_time": 7.5,
  "sla_compliance_rate": 95.5,
  "customer_satisfaction": 4.5,
  "shift_start": "09:00",
  "shift_end": "18:00",
  "working_days": [1, 2, 3, 4, 5],
  "notes": "Promoted to senior agent"
}
```

**Example Request:**
```bash
curl -X PUT "http://localhost:8000/api/v1/agents/1" \
  -H "Authorization: Bearer your-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{
    "availability": "busy",
    "tickets_resolved_today": 13
  }'
```

**Example Response:**
```json
{
  "success": true,
  "message": "Agent updated successfully",
  "data": {
    "id": 1,
    "availability": "busy",
    "tickets_resolved_today": 13,
    "updated_at": "2024-11-22T11:00:00.000000Z"
  }
}
```

---

### 5. Delete Agent
Soft delete an agent profile.

**Endpoint:** `DELETE /agents/{id}`

**Example Request:**
```bash
curl -X DELETE "http://localhost:8000/api/v1/agents/1" \
  -H "Authorization: Bearer your-jwt-token"
```

**Example Response:**
```json
{
  "success": true,
  "message": "Agent deleted successfully"
}
```

---

### 6. Update Agent Availability
Update only the agent's availability status.

**Endpoint:** `PUT /agents/{id}/availability`

**Request Body:**
```json
{
  "availability": "available"
}
```

**Availability Options:**
- `available` - Ready to take calls/tickets
- `busy` - Currently handling tickets
- `away` - On break or temporarily unavailable
- `offline` - Not logged in or off duty

**Example Request:**
```bash
curl -X PUT "http://localhost:8000/api/v1/agents/1/availability" \
  -H "Authorization: Bearer your-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{"availability": "available"}'
```

**Example Response:**
```json
{
  "success": true,
  "message": "Availability updated successfully",
  "data": {
    "id": 1,
    "availability": "available",
    "last_activity_at": "2024-11-22T11:30:00.000000Z"
  }
}
```

---

### 7. Get Agent Performance Metrics
Retrieve performance metrics for a specific agent.

**Endpoint:** `GET /agents/{id}/metrics`

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/agents/1/metrics" \
  -H "Authorization: Bearer your-jwt-token"
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "tickets_resolved_today": 12,
    "daily_target": 15,
    "progress_percentage": 80.0,
    "avg_response_time": 8.5,
    "sla_compliance_rate": 94.0,
    "customer_satisfaction": 4.3
  }
}
```

---

## Error Responses

### 404 Not Found
```json
{
  "success": false,
  "message": "Agent not found"
}
```

### 422 Validation Error
```json
{
  "success": false,
  "errors": {
    "user_id": ["This user already has an agent profile."],
    "department": ["Department is required."]
  }
}
```

### 500 Server Error
```json
{
  "success": false,
  "message": "Failed to create agent",
  "error": "Database connection error"
}
```

---

## Data Models

### Agent Model
```typescript
interface Agent {
  id: number;
  user_id: number;
  agent_code: string;
  employee_id?: string;
  department: string;
  designation?: string;
  mitel_agent_id?: string;
  mitel_extension?: string;
  mitel_queue_ids?: string[];
  status: 'active' | 'inactive' | 'suspended';
  availability: 'available' | 'busy' | 'away' | 'offline';
  skills?: string[];
  languages?: string[];
  max_concurrent_tickets: number;
  tickets_resolved_today: number;
  daily_target: number;
  avg_response_time: number;
  sla_compliance_rate: number;
  customer_satisfaction: number;
  shift_start?: string;
  shift_end?: string;
  working_days?: number[];
  notes?: string;
  last_activity_at?: string;
  created_at: string;
  updated_at: string;
  deleted_at?: string;
  user?: User;
}

interface User {
  id: number;
  name: string;
  email: string;
  role: 'agent' | 'supervisor' | 'admin';
  ignite_id?: string;
  created_at: string;
}
```

---

## Sample Users for Testing

| Role | Username | Password | Email |
|------|----------|----------|-------|
| Agent | ahmad.rahman@jkdm.gov.my | Agent@123 | ahmad.rahman@jkdm.gov.my |
| Supervisor | siti.nurhaliza@jkdm.gov.my | Super@123 | siti.nurhaliza@jkdm.gov.my |
| Agent | raj.kumar@jkdm.gov.my | Agent@123 | raj.kumar@jkdm.gov.my |
| Admin | lim.weiming@jkdm.gov.my | Admin@123 | lim.weiming@jkdm.gov.my |

---

## Workflow Examples

### 1. Register New Agent
```bash
# Step 1: Login to get JWT token
curl -X POST "http://localhost:8000/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@jkdm.gov.my",
    "password": "Admin@123"
  }'

# Step 2: Create new agent
curl -X POST "http://localhost:8000/api/v1/agents" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 5,
    "department": "Customer Service",
    "daily_target": 15
  }'
```

### 2. Agent Login and Update Availability
```bash
# Step 1: Agent logs in
curl -X POST "http://localhost:8000/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "ahmad.rahman@jkdm.gov.my",
    "password": "Agent@123"
  }'

# Step 2: Get agent profile
curl -X GET "http://localhost:8000/api/v1/auth/me" \
  -H "Authorization: Bearer {token}"

# Step 3: Update availability to available
curl -X PUT "http://localhost:8000/api/v1/agents/1/availability" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"availability": "available"}'
```

### 3. Supervisor Views Team Performance
```bash
# Step 1: Get all agents in department
curl -X GET "http://localhost:8000/api/v1/agents?department=Customer+Service&status=active" \
  -H "Authorization: Bearer {token}"

# Step 2: Get specific agent metrics
curl -X GET "http://localhost:8000/api/v1/agents/1/metrics" \
  -H "Authorization: Bearer {token}"
```

---

## Notes

- All timestamps are in UTC
- `working_days` array uses ISO 8601 standard (0 = Sunday, 1 = Monday, ... 6 = Saturday)
- `agent_code` is automatically generated using department prefix + random string
- Soft deletes are enabled - deleted agents can be restored if needed
- JWT token expires after configured time (default 60 minutes)
- Performance metrics are updated in real-time as agents resolve tickets
