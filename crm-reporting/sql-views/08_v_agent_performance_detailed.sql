-- ========================================
-- v_agent_performance_detailed
-- Enhanced Agent Performance View using AgentPerformanceByPeriodStats data
-- ========================================
-- Purpose: Comprehensive agent productivity metrics with internal/external call breakdowns
-- Data Source: mitel_agent_performance_by_period (Mitel AgentPerformanceByPeriodStats)
-- Use Cases:
--   - Agent productivity dashboard
--   - Call type distribution (ACD/Non-ACD/Outbound)
--   - Internal vs External call analysis
--   - Occupancy and utilization tracking
--   - After-call work (ACW) analysis
--   - Transfer and conference metrics
-- Optimized for: Apache Superset, PowerBI, Tableau

DROP VIEW IF EXISTS v_agent_performance_detailed CASCADE;

CREATE VIEW v_agent_performance_detailed AS
WITH daily_aggregates AS (
    SELECT
        mapbp.midnight_start_date,
        mapbp.agent_id,
        mapbp.agent_reporting,
        mapbp.employee_id,
        mapbp.day_of_week_name,
        
        -- === ACD CALL METRICS ===
        SUM(mapbp.agent_acd_count) as total_acd_calls,
        SUM(mapbp.agent_acd_duration) as total_acd_duration_sec,
        SUM(mapbp.agent_acd_time_to_answer) as total_acd_wait_time_sec,
        
        -- ACD Internal breakdown
        SUM(mapbp.agent_acd_internal_count) as acd_internal_calls,
        SUM(mapbp.agent_acd_internal_duration) as acd_internal_duration_sec,
        SUM(mapbp.agent_acd_internal_time_to_answer) as acd_internal_wait_sec,
        
        -- ACD External breakdown
        SUM(mapbp.agent_acd_external_count) as acd_external_calls,
        SUM(mapbp.agent_acd_external_duration) as acd_external_duration_sec,
        SUM(mapbp.agent_acd_external_time_to_answer) as acd_external_wait_sec,
        
        -- === NON-ACD CALL METRICS ===
        SUM(mapbp.agent_non_acd_count) as total_non_acd_calls,
        SUM(mapbp.agent_non_acd_duration) as total_non_acd_duration_sec,
        SUM(mapbp.agent_non_acd_time_to_answer) as total_non_acd_wait_time_sec,
        
        -- Non-ACD Internal breakdown
        SUM(mapbp.agent_non_acd_internal_count) as non_acd_internal_calls,
        SUM(mapbp.agent_non_acd_internal_duration) as non_acd_internal_duration_sec,
        
        -- Non-ACD External breakdown
        SUM(mapbp.agent_non_acd_external_count) as non_acd_external_calls,
        SUM(mapbp.agent_non_acd_external_duration) as non_acd_external_duration_sec,
        
        -- === OUTBOUND CALL METRICS ===
        SUM(mapbp.agent_outbound_count) as total_outbound_calls,
        SUM(mapbp.agent_outbound_duration) as total_outbound_duration_sec,
        
        -- Outbound Internal breakdown
        SUM(mapbp.agent_outbound_internal_count) as outbound_internal_calls,
        SUM(mapbp.agent_outbound_internal_duration) as outbound_internal_duration_sec,
        
        -- Outbound External breakdown
        SUM(mapbp.agent_outbound_external_count) as outbound_external_calls,
        SUM(mapbp.agent_outbound_external_duration) as outbound_external_duration_sec,
        
        -- === TRANSFER & CONFERENCE ===
        SUM(mapbp.agent_transfer_count) as total_transfers,
        SUM(mapbp.agent_conference_count) as total_conferences,
        
        -- === ABANDONED CALLS ===
        SUM(mapbp.agent_abandoned_count) as total_abandoned,
        
        -- === AFTER CALL WORK ===
        SUM(mapbp.agent_acw_duration) as total_acw_duration_sec,
        
        -- === AVAILABILITY METRICS ===
        SUM(mapbp.agent_available_duration) as total_available_duration_sec,
        SUM(mapbp.agent_unavailable_duration) as total_unavailable_duration_sec,
        SUM(mapbp.agent_busy_duration) as total_busy_duration_sec,
        SUM(mapbp.agent_system_busy_duration) as total_system_busy_duration_sec,
        SUM(mapbp.agent_login_duration) as total_login_duration_sec,
        
        -- First login and last logout for the day
        MIN(mapbp.agent_first_login_time) as first_login_time,
        MAX(mapbp.agent_last_logout_time) as last_logout_time,
        
        -- Metadata
        MAX(mapbp.synced_at) as last_synced_at,
        MAX(mapbp.updated_at) as last_updated_at
        
    FROM mitel_agent_performance_by_period mapbp
    GROUP BY 
        mapbp.midnight_start_date,
        mapbp.agent_id,
        mapbp.agent_reporting,
        mapbp.employee_id,
        mapbp.day_of_week_name
)

SELECT
    -- === IDENTIFIERS ===
    da.midnight_start_date as performance_date,
    EXTRACT(YEAR FROM da.midnight_start_date)::int as performance_year,
    EXTRACT(MONTH FROM da.midnight_start_date)::int as performance_month,
    EXTRACT(DAY FROM da.midnight_start_date)::int as performance_day,
    da.day_of_week_name,
    EXTRACT(DOW FROM da.midnight_start_date)::int as day_of_week,
    
    -- === AGENT INFORMATION (FROM CRM) ===
    a.id as agent_id,
    da.agent_id as mitel_agent_id,
    da.agent_reporting as mitel_agent_reporting,
    da.employee_id,
    u.name as agent_name,
    a.agent_code,
    a.mitel_extension as agent_extension,
    a.department as agent_department,
    a.designation as agent_designation,
    u.email as agent_email,
    
    -- === TOTAL CALL VOLUMES ===
    da.total_acd_calls,
    da.total_non_acd_calls,
    da.total_outbound_calls,
    da.total_acd_calls + da.total_non_acd_calls + da.total_outbound_calls as total_calls,
    
    -- === ACD METRICS ===
    da.total_acd_duration_sec,
    da.total_acd_wait_time_sec,
    CASE WHEN da.total_acd_calls > 0 
         THEN ROUND(da.total_acd_duration_sec::numeric / da.total_acd_calls, 2)
         ELSE 0 END as avg_acd_talk_time_sec,
    CASE WHEN da.total_acd_calls > 0 
         THEN ROUND(da.total_acd_wait_time_sec::numeric / da.total_acd_calls, 2)
         ELSE 0 END as avg_acd_wait_time_sec,
    
    -- ACD Internal/External breakdown
    da.acd_internal_calls,
    da.acd_internal_duration_sec,
    da.acd_external_calls,
    da.acd_external_duration_sec,
    
    -- === NON-ACD METRICS ===
    da.total_non_acd_duration_sec,
    da.total_non_acd_wait_time_sec,
    CASE WHEN da.total_non_acd_calls > 0 
         THEN ROUND(da.total_non_acd_duration_sec::numeric / da.total_non_acd_calls, 2)
         ELSE 0 END as avg_non_acd_talk_time_sec,
    
    -- Non-ACD Internal/External breakdown
    da.non_acd_internal_calls,
    da.non_acd_internal_duration_sec,
    da.non_acd_external_calls,
    da.non_acd_external_duration_sec,
    
    -- === OUTBOUND METRICS ===
    da.total_outbound_duration_sec,
    CASE WHEN da.total_outbound_calls > 0 
         THEN ROUND(da.total_outbound_duration_sec::numeric / da.total_outbound_calls, 2)
         ELSE 0 END as avg_outbound_talk_time_sec,
    
    -- Outbound Internal/External breakdown
    da.outbound_internal_calls,
    da.outbound_internal_duration_sec,
    da.outbound_external_calls,
    da.outbound_external_duration_sec,
    
    -- === INTERNAL VS EXTERNAL TOTALS ===
    da.acd_internal_calls + da.non_acd_internal_calls + da.outbound_internal_calls as total_internal_calls,
    da.acd_internal_duration_sec + da.non_acd_internal_duration_sec + da.outbound_internal_duration_sec as total_internal_duration_sec,
    da.acd_external_calls + da.non_acd_external_calls + da.outbound_external_calls as total_external_calls,
    da.acd_external_duration_sec + da.non_acd_external_duration_sec + da.outbound_external_duration_sec as total_external_duration_sec,
    
    -- === TRANSFER & CONFERENCE ===
    da.total_transfers,
    da.total_conferences,
    CASE WHEN da.total_acd_calls > 0 
         THEN ROUND(da.total_transfers::numeric * 100.0 / da.total_acd_calls, 2)
         ELSE 0 END as transfer_rate_pct,
    
    -- === ABANDONED CALLS ===
    da.total_abandoned,
    CASE WHEN (da.total_acd_calls + da.total_abandoned) > 0 
         THEN ROUND(da.total_abandoned::numeric * 100.0 / (da.total_acd_calls + da.total_abandoned), 2)
         ELSE 0 END as abandon_rate_pct,
    
    -- === AFTER CALL WORK (ACW) ===
    da.total_acw_duration_sec,
    CASE WHEN da.total_acd_calls > 0 
         THEN ROUND(da.total_acw_duration_sec::numeric / da.total_acd_calls, 2)
         ELSE 0 END as avg_acw_per_call_sec,
    
    -- === AVERAGE HANDLE TIME (AHT) ===
    -- AHT = Average Talk Time + Average ACW
    CASE WHEN da.total_acd_calls > 0 
         THEN ROUND((da.total_acd_duration_sec + da.total_acw_duration_sec)::numeric / da.total_acd_calls, 2)
         ELSE 0 END as avg_handle_time_sec,
    
    -- === AVAILABILITY & OCCUPANCY ===
    da.total_login_duration_sec,
    da.total_available_duration_sec,
    da.total_unavailable_duration_sec,
    da.total_busy_duration_sec,
    da.total_system_busy_duration_sec,
    
    -- Total productive time (talk + acw)
    da.total_acd_duration_sec + da.total_non_acd_duration_sec + da.total_outbound_duration_sec + da.total_acw_duration_sec as total_productive_time_sec,
    
    -- Occupancy Rate = Productive Time / Login Time
    CASE WHEN da.total_login_duration_sec > 0 
         THEN ROUND((da.total_acd_duration_sec + da.total_non_acd_duration_sec + da.total_outbound_duration_sec + da.total_acw_duration_sec)::numeric * 100.0 / da.total_login_duration_sec, 2)
         ELSE 0 END as occupancy_rate_pct,
    
    -- Utilization Rate = Talk Time / Login Time (excludes ACW)
    CASE WHEN da.total_login_duration_sec > 0 
         THEN ROUND((da.total_acd_duration_sec + da.total_non_acd_duration_sec + da.total_outbound_duration_sec)::numeric * 100.0 / da.total_login_duration_sec, 2)
         ELSE 0 END as utilization_rate_pct,
    
    -- Availability Rate = Available Time / Login Time
    CASE WHEN da.total_login_duration_sec > 0 
         THEN ROUND(da.total_available_duration_sec::numeric * 100.0 / da.total_login_duration_sec, 2)
         ELSE 0 END as availability_rate_pct,
    
    -- === LOGIN/LOGOUT TIMES ===
    da.first_login_time,
    da.last_logout_time,
    CASE WHEN da.first_login_time IS NOT NULL AND da.last_logout_time IS NOT NULL
         THEN EXTRACT(EPOCH FROM (da.last_logout_time - da.first_login_time))::int
         ELSE da.total_login_duration_sec END as shift_duration_sec,
    
    -- === METADATA ===
    da.last_synced_at,
    da.last_updated_at,
    1 as is_active

FROM daily_aggregates da
-- Join CRM agents via employee_id (case-insensitive: Mitel uppercase, CRM lowercase)
LEFT JOIN agents a ON LOWER(da.employee_id) = a.employee_id
LEFT JOIN users u ON a.user_id = u.id
WHERE a.deleted_at IS NULL OR a.id IS NULL
;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Daily agent productivity (last 30 days)
SELECT 
    performance_date,
    agent_name,
    total_calls,
    total_acd_calls,
    total_outbound_calls,
    ROUND(avg_handle_time_sec / 60.0, 2) as avg_handle_time_min,
    occupancy_rate_pct,
    transfer_rate_pct
FROM v_agent_performance_detailed
WHERE performance_date >= CURRENT_DATE - INTERVAL '30 days'
ORDER BY performance_date DESC, total_calls DESC;

-- 2. Internal vs External call distribution
SELECT 
    performance_date,
    SUM(total_internal_calls) as internal_calls,
    SUM(total_external_calls) as external_calls,
    ROUND(SUM(total_internal_duration_sec) / 60.0, 2) as internal_minutes,
    ROUND(SUM(total_external_duration_sec) / 60.0, 2) as external_minutes
FROM v_agent_performance_detailed
WHERE performance_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY performance_date
ORDER BY performance_date;

-- 3. Top performers by occupancy rate
SELECT 
    agent_name,
    agent_department,
    AVG(occupancy_rate_pct) as avg_occupancy,
    SUM(total_calls) as total_calls,
    ROUND(AVG(avg_handle_time_sec) / 60.0, 2) as avg_handle_time_min
FROM v_agent_performance_detailed
WHERE performance_date >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY agent_name, agent_department
HAVING SUM(total_calls) > 0
ORDER BY avg_occupancy DESC
LIMIT 20;

-- 4. Call type breakdown by agent
SELECT 
    agent_name,
    SUM(total_acd_calls) as acd_calls,
    SUM(total_non_acd_calls) as non_acd_calls,
    SUM(total_outbound_calls) as outbound_calls,
    ROUND(SUM(total_acd_calls)::numeric * 100.0 / NULLIF(SUM(total_calls), 0), 2) as acd_pct,
    ROUND(SUM(total_outbound_calls)::numeric * 100.0 / NULLIF(SUM(total_calls), 0), 2) as outbound_pct
FROM v_agent_performance_detailed
WHERE performance_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY agent_name
HAVING SUM(total_calls) > 0
ORDER BY SUM(total_calls) DESC;

-- 5. Day of week analysis
SELECT 
    day_of_week_name,
    AVG(total_calls) as avg_calls_per_agent,
    AVG(occupancy_rate_pct) as avg_occupancy,
    AVG(avg_handle_time_sec) / 60.0 as avg_handle_time_min,
    AVG(transfer_rate_pct) as avg_transfer_rate
FROM v_agent_performance_detailed
WHERE performance_date >= CURRENT_DATE - INTERVAL '90 days'
GROUP BY day_of_week_name, day_of_week
ORDER BY day_of_week;

-- 6. ACW analysis by agent
SELECT 
    agent_name,
    SUM(total_acd_calls) as total_calls,
    ROUND(SUM(total_acw_duration_sec) / 60.0, 2) as total_acw_minutes,
    ROUND(AVG(avg_acw_per_call_sec), 2) as avg_acw_per_call_sec,
    ROUND(SUM(total_acw_duration_sec)::numeric * 100.0 / NULLIF(SUM(total_acd_duration_sec + total_acw_duration_sec), 0), 2) as acw_pct_of_handle_time
FROM v_agent_performance_detailed
WHERE performance_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY agent_name
HAVING SUM(total_acd_calls) > 0
ORDER BY avg_acw_per_call_sec DESC;

*/
