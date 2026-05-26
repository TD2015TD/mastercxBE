-- ========================================
-- v_agent_performance_by_make_busy_dnd_code
-- Agent Performance by Make Busy / DND Code Report
-- ========================================
-- Purpose: Aggregate agent unavailability by reason code for performance analysis
-- Data Source: Mitel Agent Trace Events (aggregated)
-- Use Cases:
--   - Analyze agent unavailability patterns by reason
--   - Compare reason code usage across agents
--   - Identify most frequently used unavailability reasons
--   - Monitor average duration per reason type
--   - Performance reviews and compliance audits
-- Optimized for: Apache Superset, PowerBI, Tableau

DROP VIEW IF EXISTS v_agent_performance_by_make_busy_dnd_code CASCADE;

CREATE VIEW v_agent_performance_by_make_busy_dnd_code AS
SELECT
    -- === AGENT INFORMATION ===
    mca.reporting as reporting,
    COALESCE(u.name, CONCAT(mca.first_name, ' ', mca.last_name)) as full_name,
    a.agent_code as crm_agent_code,
    a.department as agent_department,
    
    -- === REASON INFORMATION ===
    COALESCE(mcmb.name, 'DND') as reason_type,
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
    
    -- === AGGREGATED METRICS ===
    COUNT(*) as number_of_codes_entered,
    SUM(EXTRACT(EPOCH FROM (mate.event_end_date - mate.event_start_date)))::int as duration_seconds,
    TO_CHAR(SUM(mate.event_end_date - mate.event_start_date), 'HH24:MI:SS') as duration_hhmmss,
    ROUND(AVG(EXTRACT(EPOCH FROM (mate.event_end_date - mate.event_start_date))::numeric), 2) as avg_duration_seconds,
    TO_CHAR(AVG(mate.event_end_date - mate.event_start_date), 'HH24:MI:SS') as avg_duration_hhmmss,
    
    -- === TIME PERIOD ===
    MIN(mate.event_start_date::date) as first_occurrence_date,
    MAX(mate.event_start_date::date) as last_occurrence_date,
    COUNT(DISTINCT mate.event_start_date::date) as days_active,
    
    -- === METADATA ===
    MAX(mate.synced_at) as last_synced_at,
    CURRENT_TIMESTAMP as record_generated_at

FROM mitel_data_agent_trace_events mate
-- Join Mitel config agents
INNER JOIN mitel_config_agents mca ON mate.agent_id::uuid = mca.pkey
-- Join CRM agents
LEFT JOIN agents a ON mca.fk_employee = a.employee_id::uuid
-- Join CRM users for agent name
LEFT JOIN users u ON a.user_id = u.id
-- Join make busy reasons
LEFT JOIN mitel_config_make_busy mcmb ON mate.make_busy_id = mcmb.make_busy_id

WHERE mate.make_busy_id IS NOT NULL  -- Make Busy events
   OR (mate.make_busy_id IS NULL AND mate.acd_event_id IS NULL)  -- DND events (no make busy, no ACD event)

GROUP BY 
    mca.reporting,
    mca.first_name,
    mca.last_name,
    u.name,
    a.user_id,
    a.agent_code,
    a.department,
    mcmb.name,
    mcmb.reason_code,
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
    END,
    CASE 
        WHEN mate.make_busy_id IS NOT NULL THEN 'Make Busy'
        ELSE 'DND'
    END

ORDER BY mca.reporting, number_of_codes_entered DESC;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Agent Performance Summary (All Time)
SELECT 
    reporting,
    full_name,
    agent_department,
    reason_type,
    number_of_codes_entered,
    duration_hhmmss,
    avg_duration_hhmmss,
    state_type
FROM v_agent_performance_by_make_busy_dnd_code
ORDER BY reporting, number_of_codes_entered DESC;

-- 2. Top Reason Types by Usage
SELECT 
    reason_category,
    reason_type,
    COUNT(DISTINCT reporting) as unique_agents,
    SUM(number_of_codes_entered) as total_entries,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration,
    TO_CHAR(AVG(avg_duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as overall_avg_duration
FROM v_agent_performance_by_make_busy_dnd_code
GROUP BY reason_category, reason_type
ORDER BY total_entries DESC;

-- 3. Agent Comparison by Total Unavailability
SELECT 
    reporting,
    full_name,
    agent_department,
    SUM(number_of_codes_entered) as total_code_entries,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_unavailable_time,
    ROUND(SUM(duration_seconds) / 3600.0, 2) as total_hours_unavailable,
    STRING_AGG(reason_type || ' (' || number_of_codes_entered || ')', ', ' ORDER BY number_of_codes_entered DESC) as reason_breakdown
FROM v_agent_performance_by_make_busy_dnd_code
GROUP BY reporting, full_name, agent_department
ORDER BY SUM(duration_seconds) DESC;

-- 4. Department Unavailability Analysis
SELECT 
    agent_department,
    reason_category,
    COUNT(DISTINCT reporting) as agents_using_reason,
    SUM(number_of_codes_entered) as total_entries,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration,
    TO_CHAR(AVG(avg_duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as avg_duration
FROM v_agent_performance_by_make_busy_dnd_code
WHERE agent_department IS NOT NULL
GROUP BY agent_department, reason_category
ORDER BY agent_department, total_entries DESC;

-- 5. Agents with Longest Average Duration per Reason
SELECT 
    reporting,
    full_name,
    reason_type,
    number_of_codes_entered,
    avg_duration_hhmmss,
    duration_hhmmss as total_duration
FROM v_agent_performance_by_make_busy_dnd_code
WHERE avg_duration_seconds > 1800  -- More than 30 minutes average
ORDER BY avg_duration_seconds DESC
LIMIT 20;

-- 6. Make Busy vs DND Usage Comparison
SELECT 
    reporting,
    full_name,
    agent_department,
    state_type,
    SUM(number_of_codes_entered) as total_entries,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration,
    ROUND(SUM(duration_seconds) / 3600.0, 2) as total_hours
FROM v_agent_performance_by_make_busy_dnd_code
GROUP BY reporting, full_name, agent_department, state_type
ORDER BY reporting, state_type;

-- 7. Most Frequent Reason Users
SELECT 
    reporting,
    full_name,
    reason_type,
    number_of_codes_entered,
    days_active,
    ROUND(number_of_codes_entered::numeric / days_active, 2) as avg_uses_per_day,
    duration_hhmmss,
    avg_duration_hhmmss
FROM v_agent_performance_by_make_busy_dnd_code
WHERE days_active > 0
  AND number_of_codes_entered > 50
ORDER BY number_of_codes_entered DESC
LIMIT 20;

-- 8. Reason Type Distribution by Agent
SELECT 
    reporting,
    full_name,
    COUNT(DISTINCT reason_type) as unique_reasons_used,
    SUM(number_of_codes_entered) as total_code_entries,
    STRING_AGG(reason_type, ', ' ORDER BY number_of_codes_entered DESC) as reasons_used,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_duration
FROM v_agent_performance_by_make_busy_dnd_code
GROUP BY reporting, full_name
ORDER BY unique_reasons_used DESC, total_code_entries DESC;

-- 9. Break Time Compliance Report
-- Focus on break-related reasons
SELECT 
    reporting,
    full_name,
    agent_department,
    reason_type,
    number_of_codes_entered as break_count,
    duration_hhmmss as total_break_time,
    avg_duration_hhmmss as avg_break_duration,
    days_active,
    ROUND(number_of_codes_entered::numeric / days_active, 2) as breaks_per_day
FROM v_agent_performance_by_make_busy_dnd_code
WHERE reason_category IN ('Break', 'Lunch')
ORDER BY reporting, reason_category;

-- 10. Unusual Unavailability Patterns
-- Agents with high DND usage or excessive unavailability
SELECT 
    reporting,
    full_name,
    agent_department,
    reason_type,
    number_of_codes_entered,
    duration_hhmmss,
    avg_duration_hhmmss,
    CASE 
        WHEN reason_type = 'DND' AND number_of_codes_entered > 20 THEN 'High DND Usage'
        WHEN avg_duration_seconds > 3600 THEN 'Long Duration'
        WHEN number_of_codes_entered > 100 THEN 'Frequent Usage'
        ELSE 'Normal'
    END as alert_flag
FROM v_agent_performance_by_make_busy_dnd_code
WHERE (reason_type = 'DND' AND number_of_codes_entered > 20)
   OR avg_duration_seconds > 3600
   OR number_of_codes_entered > 100
ORDER BY number_of_codes_entered DESC;

-- 11. Time Period Analysis
SELECT 
    reporting,
    full_name,
    reason_type,
    number_of_codes_entered,
    first_occurrence_date,
    last_occurrence_date,
    days_active,
    duration_hhmmss,
    ROUND(duration_seconds::numeric / days_active, 2) as avg_seconds_per_day
FROM v_agent_performance_by_make_busy_dnd_code
WHERE days_active > 30
ORDER BY reporting, number_of_codes_entered DESC;

-- 12. Department Performance Comparison
SELECT 
    agent_department,
    COUNT(DISTINCT reporting) as total_agents,
    SUM(number_of_codes_entered) as total_code_entries,
    TO_CHAR(SUM(duration_seconds) * INTERVAL '1 second', 'HH24:MI:SS') as total_unavailable_time,
    ROUND(AVG(duration_seconds), 2) as avg_duration_per_agent_sec,
    ROUND(SUM(duration_seconds) / COUNT(DISTINCT reporting) / 3600.0, 2) as avg_hours_per_agent
FROM v_agent_performance_by_make_busy_dnd_code
WHERE agent_department IS NOT NULL
GROUP BY agent_department
ORDER BY avg_hours_per_agent DESC;

*/

-- === NOTES ===
/*
Data Source:
- mitel_data_agent_trace_events: Contains agent state change events including Make Busy and DND
- mitel_config_make_busy: Make Busy reason codes and descriptions
- mitel_config_agents: Agent details from Mitel
- agents: CRM agent information for department and code

Aggregation Level:
- One row per agent per reason type
- Aggregates all historical events for each agent-reason combination

Key Metrics:
- number_of_codes_entered: How many times the agent used this reason code
- duration_seconds / duration_hhmmss: Total time spent in this unavailability state
- avg_duration_seconds / avg_duration_hhmmss: Average time per occurrence

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
- Performance reviews based on unavailability patterns
- Identify agents with excessive unavailability
- Compare reason code usage across team members
- Analyze break time compliance
- Audit unavailability reason usage
- Optimize staffing based on unavailability patterns
- Track trends in reason type usage

Filtering Recommendations:
- Filter by first_occurrence_date and last_occurrence_date for time period analysis
- Filter by agent_department for team comparisons
- Filter by reason_category for specific unavailability type analysis
*/
