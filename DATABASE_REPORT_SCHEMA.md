# CRM Database Schema - Reporting Guide

## Database Configuration

- **Database Name:** `crm_jkm`
- **Database Type:** PostgreSQL 14.22
- **Schema Name:** `public` (default PostgreSQL schema)
- **Host:** 10.13.173.65
- **Port:** 5432

---

## 1. All Tickets Report

### Database Information
- **Database:** `crm_jkm`
- **Schema:** `public`
- **Main Table:** `tickets`

### Key Columns
```
- id (Primary Key)
- ticket_number (Unique identifier)
- customer_id (Foreign Key -> customers.id)
- assigned_to (Foreign Key -> agents.id)
- created_by (Foreign Key -> agents.id)
- status (Open, In Progress, Pending, On Hold, Resolved, Closed, Cancelled)
- priority (P1, P2, P3, P4)
- department
- category
- sub_category
- subject
- description
- channel (phone, email, chat, web, walk-in)
- source
- created_at
- updated_at
- resolved_at
- closed_at
- sla_due_at
- sla_breached
- sla_duration_minutes
- first_response_at
- first_response_time_minutes
- resolution_time_minutes
- customer_rating
- customer_feedback
- deleted_at (Soft delete)
```

### Required Joins
```sql
SELECT 
    t.*,
    c.name as customer_name,
    c.email as customer_email,
    c.id_no as customer_id_no,
    a.agent_code,
    u.name as agent_name
FROM tickets t
LEFT JOIN customers c ON t.customer_id = c.id
LEFT JOIN agents a ON t.assigned_to = a.id
LEFT JOIN users u ON a.user_id = u.id
WHERE t.deleted_at IS NULL
ORDER BY t.created_at DESC;
```

---

## 2. Tickets Per Agent Report

### Database Information
- **Database:** `crm_jkm`
- **Schema:** `public`
- **Main Tables:** `tickets` + `agents` + `users`

### Key Columns
**From agents table:**
```
- id (Primary Key)
- agent_code (Unique)
- user_id (Foreign Key -> users.id)
- department
- designation
- mitel_agent_id
- status (active, inactive, suspended)
- availability (available, busy, away, offline)
```

**From tickets table:**
```
- assigned_to (Foreign Key -> agents.id)
- status
- priority
- created_at
- resolved_at
- resolution_time_minutes
- sla_breached
```

### Required Query
```sql
SELECT 
    a.id as agent_id,
    a.agent_code,
    u.name as agent_name,
    a.department,
    a.designation,
    COUNT(t.id) as total_tickets,
    COUNT(CASE WHEN t.status = 'Open' THEN 1 END) as open_tickets,
    COUNT(CASE WHEN t.status = 'In Progress' THEN 1 END) as in_progress_tickets,
    COUNT(CASE WHEN t.status = 'Pending' THEN 1 END) as pending_tickets,
    COUNT(CASE WHEN t.status = 'On Hold' THEN 1 END) as on_hold_tickets,
    COUNT(CASE WHEN t.status = 'Resolved' THEN 1 END) as resolved_tickets,
    COUNT(CASE WHEN t.status = 'Closed' THEN 1 END) as closed_tickets,
    AVG(t.resolution_time_minutes) as avg_resolution_time,
    COUNT(CASE WHEN t.sla_breached = true THEN 1 END) as sla_breaches,
    ROUND(
        COUNT(CASE WHEN t.sla_breached = false THEN 1 END)::numeric / 
        NULLIF(COUNT(t.id), 0) * 100, 
        2
    ) as sla_compliance_rate
FROM agents a
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN tickets t ON t.assigned_to = a.id AND t.deleted_at IS NULL
WHERE a.deleted_at IS NULL
GROUP BY a.id, a.agent_code, u.name, a.department, a.designation
ORDER BY total_tickets DESC;
```

---

## 3. Tickets Per Day/Month/Year/Custom Range

### Database Information
- **Database:** `crm_jkm`
- **Schema:** `public`
- **Main Table:** `tickets`

### Key Columns
```
- created_at (Timestamp for grouping)
- status
- priority
- channel
- resolved_at
- closed_at
- department
- category
```

### Queries by Time Period

#### Tickets Per Day
```sql
SELECT 
    DATE(created_at) as ticket_date,
    COUNT(*) as total_tickets,
    COUNT(CASE WHEN status = 'Open' THEN 1 END) as open_tickets,
    COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_tickets,
    COUNT(CASE WHEN status = 'Closed' THEN 1 END) as closed_tickets,
    COUNT(CASE WHEN priority = 'P1' THEN 1 END) as p1_tickets,
    COUNT(CASE WHEN priority = 'P2' THEN 1 END) as p2_tickets,
    COUNT(CASE WHEN priority = 'P3' THEN 1 END) as p3_tickets,
    COUNT(CASE WHEN priority = 'P4' THEN 1 END) as p4_tickets
FROM tickets
WHERE deleted_at IS NULL
GROUP BY DATE(created_at)
ORDER BY ticket_date DESC;
```

#### Tickets Per Month
```sql
SELECT 
    DATE_TRUNC('month', created_at) as ticket_month,
    TO_CHAR(created_at, 'YYYY-MM') as month_label,
    COUNT(*) as total_tickets,
    COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_tickets,
    COUNT(CASE WHEN status = 'Closed' THEN 1 END) as closed_tickets,
    AVG(resolution_time_minutes) as avg_resolution_time,
    COUNT(CASE WHEN sla_breached = true THEN 1 END) as sla_breaches
FROM tickets
WHERE deleted_at IS NULL
GROUP BY DATE_TRUNC('month', created_at), TO_CHAR(created_at, 'YYYY-MM')
ORDER BY ticket_month DESC;
```

#### Tickets Per Year
```sql
SELECT 
    DATE_TRUNC('year', created_at) as ticket_year,
    EXTRACT(YEAR FROM created_at) as year_label,
    COUNT(*) as total_tickets,
    COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_tickets,
    COUNT(CASE WHEN status = 'Closed' THEN 1 END) as closed_tickets,
    AVG(resolution_time_minutes) as avg_resolution_time,
    AVG(customer_rating) as avg_customer_rating
FROM tickets
WHERE deleted_at IS NULL
GROUP BY DATE_TRUNC('year', created_at), EXTRACT(YEAR FROM created_at)
ORDER BY ticket_year DESC;
```

#### Tickets Per Custom Date Range
```sql
SELECT 
    COUNT(*) as total_tickets,
    COUNT(CASE WHEN status = 'Open' THEN 1 END) as open_tickets,
    COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_tickets,
    COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_tickets,
    COUNT(CASE WHEN status = 'Closed' THEN 1 END) as closed_tickets,
    AVG(resolution_time_minutes) as avg_resolution_time,
    MIN(created_at) as first_ticket_date,
    MAX(created_at) as last_ticket_date
FROM tickets
WHERE created_at BETWEEN :start_date AND :end_date
  AND deleted_at IS NULL;

-- Example with specific dates:
-- WHERE created_at BETWEEN '2026-01-01 00:00:00' AND '2026-12-31 23:59:59'
```

---

## 4. Number of Interactions vs Tickets

### Database Information
- **Database:** `crm_jkm`
- **Schema:** `public`
- **Main Tables:** `customer_interactions` + `tickets`

### Key Columns
**From customer_interactions table:**
```
- id (Primary Key)
- ticket_id (Foreign Key -> tickets.id)
- customer_id (Foreign Key -> customers.id)
- agent_id (Foreign Key -> agents.id)
- interaction_type (call, email, chat, sms, meeting, note)
- channel (phone, email, chat, web, walk-in, social_media)
- duration_minutes
- sentiment (positive, neutral, negative)
- interaction_at
- deleted_at
```

**From tickets table:**
```
- id
- ticket_number
- status
- customer_id
- created_at
- resolved_at
```

### Required Query
```sql
SELECT 
    t.id as ticket_id,
    t.ticket_number,
    t.status,
    t.created_at as ticket_created_at,
    t.resolved_at as ticket_resolved_at,
    c.name as customer_name,
    COUNT(ci.id) as interaction_count,
    STRING_AGG(DISTINCT ci.interaction_type::text, ', ') as interaction_types,
    STRING_AGG(DISTINCT ci.channel::text, ', ') as channels_used,
    SUM(ci.duration_minutes) as total_interaction_duration,
    AVG(ci.duration_minutes) as avg_interaction_duration,
    MIN(ci.interaction_at) as first_interaction,
    MAX(ci.interaction_at) as last_interaction
FROM tickets t
LEFT JOIN customer_interactions ci ON ci.ticket_id = t.id AND ci.deleted_at IS NULL
LEFT JOIN customers c ON t.customer_id = c.id
WHERE t.deleted_at IS NULL
GROUP BY t.id, t.ticket_number, t.status, t.created_at, t.resolved_at, c.name
ORDER BY interaction_count DESC, t.created_at DESC;
```

### Summary Statistics
```sql
-- Interaction count distribution
SELECT 
    interaction_count,
    COUNT(*) as ticket_count,
    ROUND(COUNT(*)::numeric / SUM(COUNT(*)) OVER () * 100, 2) as percentage
FROM (
    SELECT 
        t.id,
        COUNT(ci.id) as interaction_count
    FROM tickets t
    LEFT JOIN customer_interactions ci ON ci.ticket_id = t.id AND ci.deleted_at IS NULL
    WHERE t.deleted_at IS NULL
    GROUP BY t.id
) subquery
GROUP BY interaction_count
ORDER BY interaction_count;
```

---

## 5. Summary by Channel

### Database Information
- **Database:** `crm_jkm`
- **Schema:** `public`
- **Main Table:** `tickets`

### Key Columns
```
- channel (phone, email, chat, web, walk-in)
- status
- priority
- created_at
- resolved_at
- resolution_time_minutes
- customer_rating
- sla_breached
- sla_duration_minutes
```

### Required Query
```sql
SELECT 
    channel,
    COUNT(*) as total_tickets,
    COUNT(CASE WHEN status = 'Open' THEN 1 END) as open_tickets,
    COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_tickets,
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_tickets,
    COUNT(CASE WHEN status = 'On Hold' THEN 1 END) as on_hold_tickets,
    COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_tickets,
    COUNT(CASE WHEN status = 'Closed' THEN 1 END) as closed_tickets,
    AVG(resolution_time_minutes) as avg_resolution_time_minutes,
    MIN(resolution_time_minutes) as min_resolution_time,
    MAX(resolution_time_minutes) as max_resolution_time,
    COUNT(CASE WHEN sla_breached = true THEN 1 END) as sla_breaches,
    COUNT(CASE WHEN sla_breached = false THEN 1 END) as sla_met,
    ROUND(
        COUNT(CASE WHEN sla_breached = false THEN 1 END)::numeric / 
        NULLIF(COUNT(*), 0) * 100, 
        2
    ) as sla_compliance_percentage,
    AVG(customer_rating) as avg_customer_rating,
    COUNT(CASE WHEN customer_rating IS NOT NULL THEN 1 END) as rated_tickets,
    COUNT(CASE WHEN priority = 'P1' THEN 1 END) as p1_count,
    COUNT(CASE WHEN priority = 'P2' THEN 1 END) as p2_count,
    COUNT(CASE WHEN priority = 'P3' THEN 1 END) as p3_count,
    COUNT(CASE WHEN priority = 'P4' THEN 1 END) as p4_count
FROM tickets
WHERE deleted_at IS NULL
GROUP BY channel
ORDER BY total_tickets DESC;
```

### Channel Performance Over Time
```sql
SELECT 
    DATE_TRUNC('month', created_at) as month,
    channel,
    COUNT(*) as ticket_count,
    AVG(resolution_time_minutes) as avg_resolution_time,
    AVG(customer_rating) as avg_rating
FROM tickets
WHERE deleted_at IS NULL
  AND created_at >= NOW() - INTERVAL '12 months'
GROUP BY DATE_TRUNC('month', created_at), channel
ORDER BY month DESC, channel;
```

---

## Important Notes

### Soft Deletes
All main tables support soft deletes. Always include `WHERE deleted_at IS NULL` in your queries to exclude deleted records.

### Date/Time Functions in PostgreSQL
- `DATE(timestamp)` - Extract date portion
- `DATE_TRUNC('day', timestamp)` - Truncate to day
- `DATE_TRUNC('month', timestamp)` - Truncate to month
- `DATE_TRUNC('year', timestamp)` - Truncate to year
- `EXTRACT(YEAR FROM timestamp)` - Extract year as integer
- `TO_CHAR(timestamp, 'YYYY-MM-DD')` - Format date as string

### Performance Indexes
The following indexes are available to optimize queries:
- `tickets.status`
- `tickets.priority`
- `tickets.department`
- `tickets.category`
- `tickets.sla_due_at`
- `tickets.sla_breached`
- `tickets.created_at`
- `customer_interactions.customer_id, interaction_at`
- `customer_interactions.agent_id, interaction_at`
- `customer_interactions.interaction_type`

### Common Filters
```sql
-- Active tickets only
WHERE status IN ('Open', 'In Progress', 'Pending')

-- High priority tickets
WHERE priority IN ('P1', 'P2')

-- SLA breached tickets
WHERE sla_breached = true

-- Recent tickets (last 30 days)
WHERE created_at >= NOW() - INTERVAL '30 days'

-- Resolved within timeframe
WHERE resolved_at BETWEEN :start_date AND :end_date
```

---

## Additional Supporting Tables

### agent_performance_metrics
Pre-calculated performance metrics for agents
```
- agent_id
- metric_date
- tickets_assigned
- tickets_resolved
- avg_resolution_time
- sla_compliance_rate
- customer_satisfaction_score
```

### agent_call_statistics
Mitel call statistics for agents
```
- agent_id
- call_date
- total_calls
- answered_calls
- missed_calls
- avg_call_duration
- total_talk_time
```

### customers
Customer master data
```
- id
- name
- email
- id_type, id_no
- company_name
- customer_type (individual, business)
- total_tickets
- satisfaction_rating
```

### agents
Agent profiles
```
- id
- user_id
- agent_code
- department
- designation
- mitel_agent_id
- status
- availability
```

---

## Sample Combined Report Query

```sql
-- Comprehensive ticket report with all related information
SELECT 
    t.ticket_number,
    t.status,
    t.priority,
    t.channel,
    t.department,
    t.category,
    t.subject,
    c.name as customer_name,
    c.email as customer_email,
    c.id_no as customer_id_no,
    c.customer_type,
    a.agent_code,
    u.name as agent_name,
    a.department as agent_department,
    t.created_at,
    t.resolved_at,
    t.closed_at,
    t.resolution_time_minutes,
    t.sla_due_at,
    t.sla_breached,
    t.customer_rating,
    COUNT(ci.id) as interaction_count,
    STRING_AGG(DISTINCT ci.interaction_type::text, ', ') as interaction_types
FROM tickets t
LEFT JOIN customers c ON t.customer_id = c.id
LEFT JOIN agents a ON t.assigned_to = a.id
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN customer_interactions ci ON ci.ticket_id = t.id AND ci.deleted_at IS NULL
WHERE t.deleted_at IS NULL
GROUP BY 
    t.id, t.ticket_number, t.status, t.priority, t.channel, t.department, 
    t.category, t.subject, c.name, c.email, c.id_no, c.customer_type,
    a.agent_code, u.name, a.department, t.created_at, t.resolved_at,
    t.closed_at, t.resolution_time_minutes, t.sla_due_at, t.sla_breached,
    t.customer_rating
ORDER BY t.created_at DESC
LIMIT 100;
```

---

**Document Version:** 1.0  
**Last Updated:** April 10, 2026  
**Database:** crm_jkm (PostgreSQL 14.22)
