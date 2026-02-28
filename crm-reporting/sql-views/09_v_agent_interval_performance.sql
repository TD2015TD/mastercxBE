-- ========================================
-- v_agent_interval_performance
-- Hourly/Interval Agent Performance Metrics
-- ========================================
-- Purpose: Time-interval granular performance for intraday monitoring
-- Data Source: mitel_agent_performance_by_period (raw intervals from Mitel)
-- Use Cases:
--   - Real-time agent monitoring
--   - Intraday performance tracking
--   - Peak hour staffing analysis
--   - Interval-by-interval productivity
--   - Break adherence monitoring
-- Optimized for: Apache Superset real-time dashboards

DROP VIEW IF EXISTS v_agent_interval_performance CASCADE;

CREATE VIEW v_agent_interval_performance AS
SELECT
    -- === TIME IDENTIFIERS ===
    mapbp.interval_start_time,
    mapbp.midnight_start_date as performance_date,
    mapbp.interval_number,
    EXTRACT(HOUR FROM mapbp.interval_start_time)::int as interval_hour,
    EXTRACT(MINUTE FROM mapbp.interval_start_time)::int as interval_minute,
    mapbp.day_of_week_name,
    
    -- === AGENT INFORMATION ===
    a.id as agent_id,
    mapbp.agent_id as mitel_agent_id,
    mapbp.agent_reporting as mitel_agent_reporting,
    mapbp.employee_id,
    u.name as agent_name,
    a.agent_code,
    a.department as agent_department,
    a.designation as agent_designation,
    
    -- === CALL VOLUMES ===
    mapbp.agent_acd_count as acd_calls,
    mapbp.agent_non_acd_count as non_acd_calls,
    mapbp.agent_outbound_count as outbound_calls,
    mapbp.agent_acd_count + mapbp.agent_non_acd_count + mapbp.agent_outbound_count as total_calls,
    
    -- === INTERNAL/EXTERNAL BREAKDOWN ===
    mapbp.agent_acd_internal_count + mapbp.agent_non_acd_internal_count + mapbp.agent_outbound_internal_count as internal_calls,
    mapbp.agent_acd_external_count + mapbp.agent_non_acd_external_count + mapbp.agent_outbound_external_count as external_calls,
    
    -- === TALK TIME ===
    mapbp.agent_acd_duration as acd_talk_time_sec,
    mapbp.agent_non_acd_duration as non_acd_talk_time_sec,
    mapbp.agent_outbound_duration as outbound_talk_time_sec,
    mapbp.agent_acd_duration + mapbp.agent_non_acd_duration + mapbp.agent_outbound_duration as total_talk_time_sec,
    
    -- === WAIT TIME ===
    mapbp.agent_acd_time_to_answer as acd_wait_time_sec,
    mapbp.agent_non_acd_time_to_answer as non_acd_wait_time_sec,
    
    -- === AFTER CALL WORK ===
    mapbp.agent_acw_duration as acw_duration_sec,
    
    -- === AVERAGES ===
    CASE WHEN mapbp.agent_acd_count > 0 
         THEN ROUND(mapbp.agent_acd_duration::numeric / mapbp.agent_acd_count, 2)
         ELSE 0 END as avg_acd_talk_time_sec,
    CASE WHEN mapbp.agent_acd_count > 0 
         THEN ROUND((mapbp.agent_acd_duration + mapbp.agent_acw_duration)::numeric / mapbp.agent_acd_count, 2)
         ELSE 0 END as avg_handle_time_sec,
    
    -- === TRANSFER & CONFERENCE ===
    mapbp.agent_transfer_count as transfers,
    mapbp.agent_conference_count as conferences,
    mapbp.agent_abandoned_count as abandoned_calls,
    
    -- === AVAILABILITY ===
    mapbp.agent_available_duration as available_time_sec,
    mapbp.agent_unavailable_duration as unavailable_time_sec,
    mapbp.agent_busy_duration as busy_time_sec,
    mapbp.agent_system_busy_duration as system_busy_time_sec,
    mapbp.agent_login_duration as login_duration_sec,
    
    -- === OCCUPANCY (INTERVAL) ===
    CASE WHEN mapbp.agent_login_duration > 0 
         THEN ROUND((mapbp.agent_acd_duration + mapbp.agent_non_acd_duration + mapbp.agent_outbound_duration + mapbp.agent_acw_duration)::numeric * 100.0 / mapbp.agent_login_duration, 2)
         ELSE 0 END as occupancy_pct,
    
    -- === LOGIN STATUS ===
    mapbp.agent_first_login_time as login_time,
    mapbp.agent_last_logout_time as logout_time,
    CASE 
        WHEN mapbp.agent_login_duration > 0 THEN 'Logged In'
        ELSE 'Logged Out'
    END as login_status,
    
    -- === METADATA ===
    mapbp.synced_at,
    mapbp.updated_at

FROM mitel_agent_performance_by_period mapbp
-- Join CRM agents
LEFT JOIN agents a ON LOWER(mapbp.employee_id) = a.employee_id AND a.deleted_at IS NULL
LEFT JOIN users u ON a.user_id = u.id
;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Real-time agent status (current hour)
SELECT 
    agent_name,
    interval_start_time,
    total_calls,
    occupancy_pct,
    login_status,
    available_time_sec / 60.0 as available_minutes
FROM v_agent_interval_performance
WHERE interval_start_time >= DATE_TRUNC('hour', NOW()) - INTERVAL '1 hour'
ORDER BY interval_start_time DESC, agent_name;

-- 2. Intraday call volume heatmap
SELECT 
    interval_hour,
    SUM(total_calls) as calls,
    AVG(occupancy_pct) as avg_occupancy,
    COUNT(DISTINCT agent_id) as active_agents
FROM v_agent_interval_performance
WHERE performance_date = CURRENT_DATE
GROUP BY interval_hour
ORDER BY interval_hour;

-- 3. Agent productivity by 30-minute intervals
SELECT 
    DATE_TRUNC('minute', interval_start_time) as interval_30min,
    agent_name,
    SUM(total_calls) as calls,
    ROUND(SUM(total_talk_time_sec) / 60.0, 2) as talk_minutes,
    AVG(occupancy_pct) as avg_occupancy
FROM v_agent_interval_performance
WHERE performance_date >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY DATE_TRUNC('minute', interval_start_time), agent_name
ORDER BY interval_30min DESC, calls DESC;

-- 4. Peak vs off-peak analysis
SELECT 
    agent_name,
    CASE WHEN interval_hour BETWEEN 9 AND 16 THEN 'Peak' ELSE 'Off-Peak' END as period_type,
    AVG(total_calls) as avg_calls_per_interval,
    AVG(occupancy_pct) as avg_occupancy,
    AVG(avg_handle_time_sec) / 60.0 as avg_handle_time_min
FROM v_agent_interval_performance
WHERE performance_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY agent_name, CASE WHEN interval_hour BETWEEN 9 AND 16 THEN 'Peak' ELSE 'Off-Peak' END
ORDER BY agent_name, period_type;

-- 5. Break adherence monitoring (intervals with zero calls but logged in)
SELECT 
    agent_name,
    interval_start_time,
    login_duration_sec / 60.0 as login_minutes,
    total_calls,
    occupancy_pct,
    available_time_sec / 60.0 as available_minutes
FROM v_agent_interval_performance
WHERE performance_date = CURRENT_DATE
  AND login_duration_sec > 0
  AND total_calls = 0
ORDER BY interval_start_time DESC, agent_name;

*/
