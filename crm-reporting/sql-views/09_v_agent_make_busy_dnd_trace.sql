-- ========================================
-- v_agent_make_busy_dnd_trace
-- Agent by Make Busy / DND Trace Report
-- ========================================
-- Purpose: Track agent unavailability periods (Make Busy and DND states)
-- Data Source: Mitel Agent Trace Events
-- Use Cases:
--   - Monitor agent availability patterns
--   - Track make busy and DND usage
--   - Analyze unavailability reasons
--   - Audit agent state changes
--   - Identify excessive unavailability
-- Optimized for: Apache Superset, PowerBI, Tableau

DROP VIEW IF EXISTS v_agent_make_busy_dnd_trace CASCADE;

CREATE VIEW v_agent_make_busy_dnd_trace AS
SELECT
    -- === AGENT INFORMATION ===
    mca.reporting as agent_reporting_id,
    u.name as full_name
    mate.agent_id as mitel_agent_id,
    a.agent_code as crm_agent_code,
    a.department as agent_department,
    
    -- === EVENT TIMESTAMPS ===
    mate.event_start_date as call_start_time,
    mate.event_end_date as end_time,
    mate.event_start_date::date as event_date,
    EXTRACT(YEAR FROM mate.event_start_date)::int as event_year,
    EXTRACT(MONTH FROM mate.event_start_date)::int as event_month,
    EXTRACT(DAY FROM mate.event_start_date)::int as event_day,
    EXTRACT(HOUR FROM mate.event_start_date)::int as event_hour,
    EXTRACT(DOW FROM mate.event_start_date)::int as day_of_week,
    CASE EXTRACT(DOW FROM mate.event_start_date)::int
        WHEN 0 THEN 'Sunday'
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
    END as day_name,
    
    -- === DURATION ===
    EXTRACT(EPOCH FROM (mate.event_end_date - mate.event_start_date))::int as duration_seconds,
    TO_CHAR(mate.event_end_date - mate.event_start_date, 'HH24:MI:SS') as duration_hhmmss,
    
    -- === REASON INFORMATION ===
    COALESCE(mcmb.name, 'Unknown') as reason_type,
    mcmb.reason_code as reason_code,
    CASE 
        WHEN mcmb.name IS NULL THEN 'DND'
        WHEN mcmb.name ILIKE '%break%' THEN 'Break'
        WHEN mcmb.name ILIKE '%lunch%' THEN 'Lunch'
        WHEN mcmb.name ILIKE '%meeting%' THEN 'Meeting'
        WHEN mcmb.name ILIKE '%training%' THEN 'Training'
        WHEN mcmb.name ILIKE '%personal%' THEN 'Personal'
        WHEN mcmb.name ILIKE '%technical%' OR mcmb.name ILIKE '%system%' THEN 'Technical Issues'
        WHEN mcmb.name ILIKE '%dnd%' OR mcmb.name ILIKE 'do not disturb' THEN 'DND'
        ELSE 'Other'
    END as reason_category,
    
    -- === STATE TYPE ===
    CASE 
        WHEN mate.make_busy_id IS NOT NULL THEN 'Make Busy'
        ELSE 'DND'
    END as state_type,
    
    -- === QUEUE INFORMATION ===
    mcq.name as queue_name,
    mcq.reporting_id as queue_reporting_id,
    
    -- === METADATA ===
    mate.synced_at as record_synced_at,
    CURRENT_TIMESTAMP as record_generated_at

FROM mitel_data_agent_trace_events mate
-- Join Mitel config agents
INNER JOIN mitel_config_agents mca ON mate.agent_id = mca.pkey
-- Join CRM agents
LEFT JOIN agents a ON LOWER(mca.fk_employee) = a.employee_id
-- Join CRM users for agent name
LEFT JOIN users u ON a.user_id = u.id
-- Join make busy reasons
LEFT JOIN mitel_config_make_busy mcmb ON mate.make_busy_id = mcmb.make_busy_id
-- Join queue information
LEFT JOIN mitel_config_queues mcq ON mate.queue_id = mcq.queue_id

WHERE mate.make_busy_id IS NOT NULL  -- Make Busy events
   OR (mate.make_busy_id IS NULL AND mate.acd_event_id IS NULL)  -- DND events (no make busy, no ACD event)

ORDER BY mate.event_start_date DESC, mca.reporting;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Today's Make Busy / DND Activity
SELECT 
    agent_reporting_id,
    full_name,
    call_start_time,
    end_time,
    duration_hhmmss,
    reason_type,
    reason_category,
    state_type
FROM v_agent_make_busy_dnd_trace
WHERE event_date = CURRENT_DATE
ORDER BY call_start_time DESC;

-- 2. Agent Unavailability Summary (Last 7 Days)
SELECT 
    full_name,
    agent_reporting_id,
    agent_department,
    state_type,
    COUNT(*) as total_occurrences,
    SUM(duration_seconds) as total_duration_sec,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration_hhmmss,
    ROUND(AVG(duration_seconds), 2) as avg_duration_sec,
    TO_CHAR(AVG(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as avg_duration_hhmmss
FROM v_agent_make_busy_dnd_trace
WHERE event_date >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY full_name, agent_reporting_id, agent_department, state_type
ORDER BY total_duration_sec DESC;

-- 3. Top Unavailability Reasons (Last 30 Days)
SELECT 
    reason_category,
    reason_type,
    COUNT(*) as occurrence_count,
    COUNT(DISTINCT agent_reporting_id) as unique_agents,
    SUM(duration_seconds) as total_duration_sec,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration_hhmmss,
    ROUND(AVG(duration_seconds), 2) as avg_duration_sec
FROM v_agent_make_busy_dnd_trace
WHERE event_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY reason_category, reason_type
ORDER BY occurrence_count DESC
LIMIT 10;

-- 4. Hourly Unavailability Pattern
SELECT 
    event_hour,
    day_name,
    state_type,
    COUNT(*) as occurrence_count,
    ROUND(AVG(duration_seconds), 2) as avg_duration_sec
FROM v_agent_make_busy_dnd_trace
WHERE event_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY event_hour, day_name, state_type
ORDER BY event_hour, 
         CASE day_name 
            WHEN 'Monday' THEN 1 
            WHEN 'Tuesday' THEN 2 
            WHEN 'Wednesday' THEN 3 
            WHEN 'Thursday' THEN 4 
            WHEN 'Friday' THEN 5 
            WHEN 'Saturday' THEN 6 
            WHEN 'Sunday' THEN 7 
         END;

-- 5. Agent Availability Compliance Report
-- Identify agents with excessive unavailability
SELECT 
    full_name,
    agent_reporting_id,
    agent_department,
    COUNT(*) as total_unavailable_events,
    SUM(duration_seconds) as total_unavailable_seconds,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_unavailable_time,
    ROUND(SUM(duration_seconds) / 3600.0, 2) as total_unavailable_hours,
    -- Flag agents with > 2 hours unavailable per day on average
    CASE 
        WHEN (SUM(duration_seconds) / 3600.0) / COUNT(DISTINCT event_date) > 2 
        THEN 'High Unavailability'
        WHEN (SUM(duration_seconds) / 3600.0) / COUNT(DISTINCT event_date) > 1 
        THEN 'Moderate Unavailability'
        ELSE 'Normal'
    END as availability_status
FROM v_agent_make_busy_dnd_trace
WHERE event_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY full_name, agent_reporting_id, agent_department
HAVING SUM(duration_seconds) > 0
ORDER BY total_unavailable_seconds DESC;

-- 6. Reason Type Breakdown by Agent
SELECT 
    full_name,
    agent_reporting_id,
    reason_category,
    COUNT(*) as usage_count,
    SUM(duration_seconds) as total_duration_sec,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration_hhmmss,
    ROUND(AVG(duration_seconds) / 60.0, 2) as avg_duration_minutes
FROM v_agent_make_busy_dnd_trace
WHERE event_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY full_name, agent_reporting_id, reason_category
ORDER BY full_name, usage_count DESC;

-- 7. Daily Unavailability Trend
SELECT 
    event_date,
    day_name,
    state_type,
    COUNT(*) as total_events,
    COUNT(DISTINCT agent_reporting_id) as agents_affected,
    SUM(duration_seconds) as total_duration_sec,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration_hhmmss,
    ROUND(AVG(duration_seconds) / 60.0, 2) as avg_duration_minutes
FROM v_agent_make_busy_dnd_trace
WHERE event_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY event_date, day_name, state_type
ORDER BY event_date DESC, state_type;

-- 8. Department Unavailability Comparison
SELECT 
    agent_department,
    state_type,
    COUNT(*) as total_events,
    COUNT(DISTINCT agent_reporting_id) as unique_agents,
    SUM(duration_seconds) as total_duration_sec,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration_hhmmss,
    ROUND(AVG(duration_seconds) / 60.0, 2) as avg_duration_minutes
FROM v_agent_make_busy_dnd_trace
WHERE event_date >= CURRENT_DATE - INTERVAL '30 days'
  AND agent_department IS NOT NULL
GROUP BY agent_department, state_type
ORDER BY total_duration_sec DESC;

-- 9. Long Duration Make Busy / DND Events (> 30 minutes)
SELECT 
    agent_reporting_id,
    full_name,
    call_start_time,
    end_time,
    duration_hhmmss,
    reason_type,
    state_type,
    queue_name
FROM v_agent_make_busy_dnd_trace
WHERE duration_seconds > 1800  -- 30 minutes
  AND event_date >= CURRENT_DATE - INTERVAL '7 days'
ORDER BY duration_seconds DESC;

-- 10. Frequent State Changers
-- Agents who frequently switch to Make Busy / DND
SELECT 
    full_name,
    agent_reporting_id,
    agent_department,
    COUNT(*) as state_change_count,
    ROUND(COUNT(*)::numeric / COUNT(DISTINCT event_date), 2) as avg_changes_per_day,
    STRING_AGG(DISTINCT reason_category, ', ') as reasons_used
FROM v_agent_make_busy_dnd_trace
WHERE event_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY full_name, agent_reporting_id, agent_department
HAVING COUNT(*) > 20
ORDER BY state_change_count DESC;

*/

-- === NOTES ===
/*
Data Source:
- mitel_data_agent_trace_events: Contains agent state change events including Make Busy and DND
- mitel_config_make_busy: Make Busy reason codes and descriptions
- mitel_config_agents: Agent details from Mitel
- agents: CRM agent information for department and code

State Types:
- Make Busy: Agent manually set themselves to unavailable with a specific reason
- DND (Do Not Disturb): Agent in DND mode (no make busy reason, no ACD event)

Reason Categories (Auto-classified):
- Break: Coffee break, restroom break
- Lunch: Lunch break
- Meeting: Team meetings, training sessions
- Training: Training activities
- Personal: Personal matters
- Technical Issues: System issues, technical problems
- DND: Do Not Disturb mode
- Other: Other reasons not categorized

Use Cases:
- Monitor agent availability compliance
- Identify excessive unavailability patterns
- Analyze break time usage
- Audit state change reasons
- Optimize staffing based on unavailability patterns
- Track DND usage
*/
