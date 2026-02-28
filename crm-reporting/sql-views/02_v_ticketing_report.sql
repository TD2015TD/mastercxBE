-- ========================================
-- v_ticketing_report
-- COMPREHENSIVE TICKET REPORTING VIEW
-- ========================================
-- Purpose: Full-detail ticket view supporting all legacy report types
-- Covers:
--   - Ticket Summary By Status
--   - Incident Aging By Status
--   - Summary By Incident Type (Category)
--   - Summary By Channel
--   - Incident Closed By Date
--   - Incident Details
--   - Summary By Date
--   - Summary By Agent
--   - Incident Details By Agent
--   - Incident Details By Date
--   - Summary By Case Category
--   - Overdue / SLA Breach (Laporan Kehadiran Kerja Lebih Masa)
-- Criteria supported: Date ranges, Ticket No, Assign Level, Created By,
--   Assign Group, Assign Individual, Status, Customer Name, Agency,
--   Case Category, Sub Category, Type
-- Optimized for: Apache Superset, PowerBI, Tableau

DROP VIEW IF EXISTS ticketing_report;
DROP VIEW IF EXISTS v_ticketing_report;

CREATE VIEW v_ticketing_report AS
SELECT

    -- === IDENTIFIERS ===
    t.id                            AS ticket_id,
    t.ticket_number,
    t.call_id,
    t.case_id,

    -- === TICKET CLASSIFICATION ===
    t.status                        AS ticket_status,
    t.priority,
    t.urgency,
    t.impact,
    t.channel,
    t.source,
    t.department                    AS agency,           -- maps to "Agency" in legacy
    t.category                      AS case_category,
    t.sub_category                  AS sub_category_1,
    NULL::text                      AS sub_category_2,   -- not in current schema
    t.subject,
    t.description,
    t.resolution_notes,

    -- === ASSIGNED AGENT (Assign Individual / Assign Group / Assign Level) ===
    a_assigned.id                   AS assigned_agent_id,
    u_assigned.name                 AS assigned_agent_name,    -- Assign Individual
    a_assigned.agent_code           AS assigned_agent_code,
    a_assigned.employee_id          AS assigned_employee_id,
    a_assigned.department           AS assigned_group,          -- Assign Group
    a_assigned.designation          AS assign_level,            -- Assign Level

    -- === CREATED BY AGENT ===
    a_created.id                    AS created_by_agent_id,
    u_created.name                  AS created_by_name,
    a_created.agent_code            AS created_by_agent_code,
    a_created.department            AS created_by_department,

    -- === CUSTOMER INFORMATION ===
    t.customer_id,
    c.name                          AS customer_name,
    c.email                         AS customer_email,
    NULL::text                      AS customer_phone,           -- removed from schema

    -- === KEY DATES (maps to legacy date filter criteria) ===
    t.created_at                    AS ticket_log_date,          -- Ticket Log Date
    t.updated_at                    AS last_update_date,         -- Ticket Last Update Date
    t.first_response_at,
    t.resolved_at                   AS actual_end_date,          -- Actual End Date
    t.closed_at,

    -- Convenient date-only columns for grouping
    DATE(t.created_at)              AS created_date,
    DATE(t.resolved_at)             AS resolved_date,
    DATE(t.closed_at)               AS closed_date,
    DATE(t.updated_at)              AS last_update_date_only,

    -- Time components for drill-down
    EXTRACT(YEAR  FROM t.created_at)::int   AS created_year,
    EXTRACT(MONTH FROM t.created_at)::int   AS created_month,
    EXTRACT(DAY   FROM t.created_at)::int   AS created_day,
    EXTRACT(DOW   FROM t.created_at)::int   AS created_day_of_week,
    TO_CHAR(t.created_at, 'Month')          AS created_month_name,

    -- === AGE / INCIDENT AGING ===
    -- Age in days from creation to resolution/closure, or to now if still open
    ROUND(
        CASE
            WHEN t.resolved_at IS NOT NULL
                THEN EXTRACT(EPOCH FROM (t.resolved_at - t.created_at)) / 86400
            WHEN t.closed_at IS NOT NULL
                THEN EXTRACT(EPOCH FROM (t.closed_at   - t.created_at)) / 86400
            ELSE EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - t.created_at)) / 86400
        END::numeric, 2
    )                                       AS age_days,

    -- Aging bucket for "Incident Aging By Status" report
    CASE
        WHEN t.status IN ('Resolved', 'Closed', 'Cancelled') THEN 'Closed/Resolved'
        WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - t.created_at)) / 86400 <= 1  THEN '0-1 Day'
        WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - t.created_at)) / 86400 <= 3  THEN '1-3 Days'
        WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - t.created_at)) / 86400 <= 7  THEN '3-7 Days'
        WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - t.created_at)) / 86400 <= 14 THEN '7-14 Days'
        WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - t.created_at)) / 86400 <= 30 THEN '14-30 Days'
        ELSE '>30 Days'
    END                                     AS aging_bucket,

    -- === SLA INFORMATION ===
    t.sla_due_at,
    t.sla_duration_minutes,
    t.sla_breached,
    t.sla_remaining_minutes,
    t.first_response_time_minutes,
    t.resolution_time_minutes,

    -- === FLAGS / INDICATORS ===
    CASE WHEN t.status IN ('Resolved', 'Closed')         THEN 1 ELSE 0 END  AS is_closed,
    CASE WHEN t.status IN ('Resolved', 'Closed', 'Cancelled') THEN 0 ELSE 1 END AS is_open,
    CASE WHEN t.sla_breached = true                      THEN 1 ELSE 0 END  AS is_sla_breached,
    CASE WHEN t.status NOT IN ('Resolved', 'Closed', 'Cancelled')
          AND t.sla_due_at < CURRENT_TIMESTAMP           THEN 1 ELSE 0 END  AS is_overdue,
    CASE WHEN t.reopened_count > 0                       THEN 1 ELSE 0 END  AS is_reopened,

    -- === CUSTOMER SATISFACTION ===
    t.customer_rating,
    t.customer_feedback,
    CASE
        WHEN t.customer_rating >= 4 THEN 'High (4-5)'
        WHEN t.customer_rating >= 3 THEN 'Medium (3)'
        WHEN t.customer_rating >= 1 THEN 'Low (1-2)'
        ELSE 'Not Rated'
    END                                     AS csat_category,

    -- === MISC ===
    t.reopened_count,
    t.tags,

    CURRENT_TIMESTAMP                       AS record_generated_at

FROM tickets t
LEFT JOIN agents   a_assigned ON t.assigned_to  = a_assigned.id AND a_assigned.deleted_at IS NULL
LEFT JOIN users    u_assigned ON a_assigned.user_id = u_assigned.id
LEFT JOIN agents   a_created  ON t.created_by   = a_created.id  AND a_created.deleted_at  IS NULL
LEFT JOIN users    u_created  ON a_created.user_id  = u_created.id
LEFT JOIN customers c         ON t.customer_id  = c.id           AND c.deleted_at          IS NULL

WHERE t.deleted_at IS NULL;


-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Ticket Summary By Status
SELECT
    ticket_status,
    COUNT(*) AS total,
    SUM(is_sla_breached) AS sla_breached,
    SUM(is_overdue) AS overdue
FROM v_ticketing_report
GROUP BY ticket_status
ORDER BY total DESC;

-- 2. Incident Aging By Status
SELECT
    ticket_status,
    aging_bucket,
    COUNT(*) AS count
FROM v_ticketing_report
WHERE is_open = 1
GROUP BY ticket_status, aging_bucket
ORDER BY ticket_status,
    CASE aging_bucket
        WHEN '0-1 Day'    THEN 1
        WHEN '1-3 Days'   THEN 2
        WHEN '3-7 Days'   THEN 3
        WHEN '7-14 Days'  THEN 4
        WHEN '14-30 Days' THEN 5
        ELSE 6
    END;

-- 3. Summary By Incident Type (Case Category)
SELECT
    case_category,
    sub_category_1,
    COUNT(*) AS total,
    SUM(is_closed) AS closed,
    SUM(is_sla_breached) AS sla_breached
FROM v_ticketing_report
WHERE ticket_log_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY case_category, sub_category_1
ORDER BY total DESC;

-- 4. Summary By Channel
SELECT
    channel,
    COUNT(*) AS total,
    SUM(is_closed) AS closed,
    AVG(resolution_time_minutes) AS avg_resolution_mins
FROM v_ticketing_report
WHERE ticket_log_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY channel
ORDER BY total DESC;

-- 5. Incident Closed By Date
SELECT
    closed_date,
    COUNT(*) AS closed_count,
    AVG(age_days) AS avg_age_days,
    AVG(resolution_time_minutes) AS avg_resolution_mins
FROM v_ticketing_report
WHERE is_closed = 1
  AND closed_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY closed_date
ORDER BY closed_date DESC;

-- 6. Incident Details (full listing with filters)
SELECT
    ticket_number,
    ticket_status,
    priority,
    case_category,
    sub_category_1,
    assigned_agent_name,
    assigned_group,
    customer_name,
    ticket_log_date,
    actual_end_date,
    age_days,
    is_sla_breached
FROM v_ticketing_report
WHERE ticket_log_date BETWEEN '2026-01-01' AND '2026-01-31'
ORDER BY ticket_log_date DESC;

-- 7. Summary By Date
SELECT
    created_date,
    COUNT(*) AS total_created,
    SUM(is_closed) AS total_closed,
    SUM(is_sla_breached) AS sla_breached
FROM v_ticketing_report
WHERE ticket_log_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY created_date
ORDER BY created_date DESC;

-- 8. Summary By Agent
SELECT
    assigned_agent_name,
    assigned_group,
    assign_level,
    COUNT(*) AS total_assigned,
    SUM(is_closed) AS resolved,
    SUM(is_sla_breached) AS sla_breached,
    ROUND(AVG(resolution_time_minutes), 2) AS avg_resolution_mins,
    ROUND(AVG(CASE WHEN customer_rating IS NOT NULL THEN customer_rating END), 2) AS avg_csat
FROM v_ticketing_report
WHERE ticket_log_date >= CURRENT_DATE - INTERVAL '30 days'
  AND assigned_agent_id IS NOT NULL
GROUP BY assigned_agent_name, assigned_group, assign_level
ORDER BY total_assigned DESC;

-- 9. Incident Details By Agent
SELECT
    assigned_agent_name,
    assigned_group,
    ticket_number,
    ticket_status,
    case_category,
    customer_name,
    ticket_log_date,
    actual_end_date,
    age_days
FROM v_ticketing_report
WHERE ticket_log_date >= CURRENT_DATE - INTERVAL '30 days'
  AND assigned_agent_id IS NOT NULL
ORDER BY assigned_agent_name, ticket_log_date DESC;

-- 10. Incident Details By Date
SELECT
    created_date,
    ticket_number,
    ticket_status,
    priority,
    assigned_agent_name,
    customer_name,
    case_category,
    age_days,
    is_sla_breached
FROM v_ticketing_report
WHERE ticket_log_date >= CURRENT_DATE - INTERVAL '30 days'
ORDER BY created_date DESC, ticket_log_date DESC;

-- 11. Summary By Case Category
SELECT
    case_category,
    sub_category_1,
    COUNT(*) AS total,
    SUM(is_closed) AS closed,
    SUM(is_overdue) AS overdue,
    ROUND(AVG(age_days), 2) AS avg_age_days
FROM v_ticketing_report
GROUP BY case_category, sub_category_1
ORDER BY total DESC;

-- 12. Overdue / SLA Breach Report (Laporan Kehadiran Kerja Lebih Masa proxy)
SELECT
    ticket_number,
    ticket_status,
    assigned_agent_name,
    assigned_group,
    customer_name,
    ticket_log_date,
    sla_due_at,
    age_days,
    is_overdue,
    is_sla_breached
FROM v_ticketing_report
WHERE (is_overdue = 1 OR is_sla_breached = 1)
  AND ticket_log_date >= CURRENT_DATE - INTERVAL '30 days'
ORDER BY age_days DESC;

*/
