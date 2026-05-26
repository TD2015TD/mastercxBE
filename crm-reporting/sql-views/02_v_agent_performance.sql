-- ========================================
-- v_agent_performance
-- AGENT PERFORMANCE DATA (DAILY AGENT METRICS)
-- ========================================
-- Purpose: Agent productivity view for Ketua Unit / Ops Manager
-- Use Cases:
--   - Agent productivity ranking
--   - Occupancy rate calculation
--   - Utilisation per shift
--   - Agent availability chart
--   - Performance trend analysis
-- Optimized for: Apache Superset, PowerBI, Tableau

CREATE OR REPLACE VIEW v_agent_performance AS
WITH mitel_agent_daily AS (
    -- Mitel agent performance aggregated daily
    SELECT
        map.agent_id as mitel_agent_id,
        DATE(map.period_date) as metric_date,
        
        -- Time metrics (seconds)
        SUM(COALESCE(map.shift_duration, 0)) as shift_duration_sec,
        SUM(COALESCE(map.idle_duration, 0)) as idle_duration_sec,
        SUM(COALESCE(map.acd_duration, 0)) as on_call_time_sec,
        0 as after_call_work_time_sec,  -- ACW not tracked in Mitel agent performance table
        
        -- Available time (ready for calls)
        SUM(COALESCE(map.shift_duration, 0)) - 
        SUM(COALESCE(map.idle_duration, 0)) as ready_time_sec,
        
        -- Not ready time (make busy)
        SUM(COALESCE(map.make_busy_duration, 0)) as not_ready_time_sec,
        
        -- Call counts
        SUM(COALESCE(map.acd_count, 0)) as calls_handled,
        SUM(COALESCE(map.acd_count, 0)) as calls_answered,  -- ACD calls are answered
        SUM(COALESCE(map.make_busy_count, 0)) as make_busy_count,
        
        -- Login events
        COUNT(DISTINCT DATE(map.period_date)) as days_worked
        
    FROM mitel_data_agent_performance map
    WHERE map.period_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY map.agent_id, DATE(map.period_date)
),

mitel_login_events AS (
    -- Agent login/logout times from Mitel events
    SELECT
        mae.agent_id as mitel_agent_id,
        DATE(mae.event_date) as metric_date,
        
        -- First login & last logout of the day
        MIN(CASE WHEN mae.event_type = 'login' THEN mae.event_date END) as first_login_time,
        MAX(CASE WHEN mae.event_type = 'logout' THEN mae.event_date END) as last_logout_time,
        
        -- Login count (multiple sessions)
        COUNT(CASE WHEN mae.event_type = 'login' THEN 1 END) as login_count,
        COUNT(CASE WHEN mae.event_type = 'logout' THEN 1 END) as logout_count
        
    FROM mitel_data_agent_events mae
    WHERE mae.event_type IN ('login', 'logout')
      AND mae.event_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY mae.agent_id, DATE(mae.event_date)
),

crm_agent_calls AS (
    -- CRM customer interactions - call metrics
    SELECT
        ci.agent_id,
        DATE(ci.interaction_at) as metric_date,
        
        -- Call counts
        COUNT(CASE WHEN ci.interaction_type = 'call' THEN 1 END) as crm_calls_total,
        COUNT(CASE WHEN ci.interaction_type = 'call' 
                   AND ci.metadata ->> 'callState' = 'answered' THEN 1 END) as crm_calls_answered,
        COUNT(CASE WHEN ci.interaction_type = 'call' 
                   AND ci.metadata ->> 'callState' = 'missed' THEN 1 END) as crm_calls_missed,
        
        -- Call time metrics
        SUM(CASE WHEN ci.interaction_type = 'call' 
                 AND ci.metadata ->> 'callState' = 'answered'
            THEN COALESCE((ci.metadata ->> 'callDuration')::int, 0) 
            ELSE 0 END) as crm_talk_time_sec,
        SUM(CASE WHEN ci.interaction_type = 'call' 
                 AND ci.metadata ->> 'callState' = 'answered'
            THEN COALESCE((ci.metadata ->> 'acwTime')::int, 0) 
            ELSE 0 END) as crm_acw_time_sec
            
    FROM customer_interactions ci
    WHERE ci.agent_id IS NOT NULL
      AND ci.interaction_at >= CURRENT_DATE - INTERVAL '90 days'
      AND ci.deleted_at IS NULL
    GROUP BY ci.agent_id, DATE(ci.interaction_at)
)

-- === MAIN QUERY ===
SELECT
    -- === AGENT IDENTIFIERS ===
    a.id as agent_id,
    a.mitel_agent_id,
    a.employee_id as agent_extension,
    u.name as agent_name,
    u.email as agent_email,
    a.department,
    a.designation as agent_role,
    
    -- === DATE DIMENSION ===
    COALESCE(mad.metric_date, mle.metric_date, cac.metric_date) as metric_date,
    EXTRACT(YEAR FROM COALESCE(mad.metric_date, mle.metric_date, cac.metric_date))::int as year,
    EXTRACT(MONTH FROM COALESCE(mad.metric_date, mle.metric_date, cac.metric_date))::int as month,
    EXTRACT(DAY FROM COALESCE(mad.metric_date, mle.metric_date, cac.metric_date))::int as day,
    EXTRACT(DOW FROM COALESCE(mad.metric_date, mle.metric_date, cac.metric_date))::int as day_of_week,
    CASE EXTRACT(DOW FROM COALESCE(mad.metric_date, mle.metric_date, cac.metric_date))::int
        WHEN 0 THEN 'Sunday'
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
    END as day_name,
    
    -- === LOGIN/LOGOUT TIMES ===
    mle.first_login_time as login_time,
    mle.last_logout_time as logout_time,
    mle.login_count,
    
    -- Calculated shift hours (login to logout)
    CASE 
        WHEN mle.first_login_time IS NOT NULL AND mle.last_logout_time IS NOT NULL
        THEN EXTRACT(EPOCH FROM (mle.last_logout_time - mle.first_login_time))::int
        ELSE COALESCE(mad.shift_duration_sec, 0)
    END as total_shift_time_sec,
    
    -- === TIME METRICS (SECONDS) ===
    COALESCE(mad.shift_duration_sec, 0) as shift_duration_sec,
    COALESCE(mad.ready_time_sec, 0) as ready_time_sec,
    COALESCE(mad.not_ready_time_sec, 0) as not_ready_time_sec,
    COALESCE(mad.idle_duration_sec, 0) as idle_time_sec,
    COALESCE(mad.on_call_time_sec, 0) as on_call_time_sec,
    COALESCE(mad.after_call_work_time_sec, 0) as after_call_work_time_sec,
    
    -- === TIME METRICS (FORMATTED HH:MM:SS) ===
    TO_CHAR(INTERVAL '1 second' * COALESCE(mad.shift_duration_sec, 0), 'HH24:MI:SS') as shift_duration_formatted,
    TO_CHAR(INTERVAL '1 second' * COALESCE(mad.ready_time_sec, 0), 'HH24:MI:SS') as ready_time_formatted,
    TO_CHAR(INTERVAL '1 second' * COALESCE(mad.on_call_time_sec, 0), 'HH24:MI:SS') as on_call_time_formatted,
    TO_CHAR(INTERVAL '1 second' * COALESCE(mad.after_call_work_time_sec, 0), 'HH24:MI:SS') as after_call_work_time_formatted,
    
    -- === CALL COUNTS (MITEL) ===
    COALESCE(mad.calls_handled, 0) as calls_handled,
    COALESCE(mad.calls_answered, 0) as calls_answered,
    0 as calls_missed,  -- Mitel doesn't track missed directly, use CRM data
    
    -- === CALL COUNTS (CRM) ===
    COALESCE(cac.crm_calls_answered, 0) as crm_calls_answered,
    COALESCE(cac.crm_calls_missed, 0) as crm_calls_missed,
    
    -- === COMBINED CALL COUNTS ===
    COALESCE(mad.calls_answered, 0) + COALESCE(cac.crm_calls_answered, 0) as total_calls_answered,
    COALESCE(cac.crm_calls_missed, 0) as total_calls_missed,
    
    -- === PRODUCTIVITY METRICS ===
    -- Productive time = on call (ACW not available in Mitel agent performance)
    COALESCE(mad.on_call_time_sec, 0) as productive_time_sec,
    
    -- Occupancy Rate = On Call / Available Time * 100
    CASE 
        WHEN COALESCE(mad.ready_time_sec, 0) > 0 
        THEN ROUND(
            COALESCE(mad.on_call_time_sec, 0)::numeric 
            * 100.0 / NULLIF(mad.ready_time_sec, 0), 2)
        ELSE 0 
    END as occupancy_rate_pct,
    
    -- Utilization Rate = Productive Time / Shift Time * 100
    CASE 
        WHEN COALESCE(mad.shift_duration_sec, 0) > 0 
        THEN ROUND(
            COALESCE(mad.on_call_time_sec, 0)::numeric 
            * 100.0 / NULLIF(mad.shift_duration_sec, 0), 2)
        ELSE 0 
    END as utilization_rate_pct,
    
    -- Idle Rate = Idle Time / Shift Time * 100
    CASE 
        WHEN COALESCE(mad.shift_duration_sec, 0) > 0 
        THEN ROUND(
            COALESCE(mad.idle_duration_sec, 0)::numeric 
            * 100.0 / NULLIF(mad.shift_duration_sec, 0), 2)
        ELSE 0 
    END as idle_rate_pct,
    
    -- === EFFICIENCY METRICS ===
    -- Average Handle Time (talk time only - ACW not available)
    CASE 
        WHEN COALESCE(mad.calls_handled, 0) > 0 
        THEN ROUND(
            COALESCE(mad.on_call_time_sec, 0)::numeric 
            / NULLIF(mad.calls_handled, 0), 2)
        ELSE 0 
    END as avg_handle_time_sec,
    
    -- Average Talk Time
    CASE 
        WHEN COALESCE(mad.calls_handled, 0) > 0 
        THEN ROUND(
            COALESCE(mad.on_call_time_sec, 0)::numeric 
            / NULLIF(mad.calls_handled, 0), 2)
        ELSE 0 
    END as avg_talk_time_sec,
    
    -- Average ACW Time
    CASE 
        WHEN COALESCE(mad.calls_handled, 0) > 0 
        THEN ROUND(
            COALESCE(mad.after_call_work_time_sec, 0)::numeric 
            / NULLIF(mad.calls_handled, 0), 2)
        ELSE 0 
    END as avg_acw_time_sec,
    
    -- === PERFORMANCE RATING ===
    CASE 
        WHEN COALESCE(mad.shift_duration_sec, 0) = 0 THEN 'No Data'
        WHEN COALESCE(mad.on_call_time_sec, 0)::numeric 
             * 100.0 / NULLIF(mad.shift_duration_sec, 0) >= 85 THEN 'Excellent'
        WHEN COALESCE(mad.on_call_time_sec, 0)::numeric 
             * 100.0 / NULLIF(mad.shift_duration_sec, 0) >= 70 THEN 'Good'
        WHEN COALESCE(mad.on_call_time_sec, 0)::numeric 
             * 100.0 / NULLIF(mad.shift_duration_sec, 0) >= 50 THEN 'Fair'
        ELSE 'Needs Improvement'
    END as performance_rating,
    
    -- === AGENT STATUS ===
    a.status as agent_status,
    CASE WHEN a.deleted_at IS NULL THEN 1 ELSE 0 END as is_active

FROM mitel_agent_daily mad
LEFT JOIN mitel_login_events mle ON mad.mitel_agent_id = mle.mitel_agent_id 
    AND mad.metric_date = mle.metric_date
LEFT JOIN mitel_config_agents mca ON mad.mitel_agent_id::uuid = mca.fk_master_agent
LEFT JOIN agents a ON mca.fk_employee = a.employee_id::uuid
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN crm_agent_calls cac ON a.id = cac.agent_id 
    AND mad.metric_date = cac.metric_date

WHERE mad.metric_date IS NOT NULL
  AND (a.deleted_at IS NULL OR a.id IS NULL)
;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Agent productivity ranking (last 30 days)
SELECT 
    agent_name,
    AVG(utilization_rate_pct) as avg_utilization,
    AVG(occupancy_rate_pct) as avg_occupancy,
    SUM(calls_handled) as total_calls,
    AVG(avg_handle_time_sec) as avg_aht
FROM v_agent_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY agent_name
ORDER BY avg_utilization DESC;

-- 2. Daily utilization trend
SELECT 
    metric_date,
    agent_name,
    utilization_rate_pct,
    calls_handled
FROM v_agent_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '30 days'
ORDER BY metric_date DESC, agent_name;

-- 3. Occupancy rate by department
SELECT 
    department,
    AVG(occupancy_rate_pct) as avg_occupancy,
    AVG(utilization_rate_pct) as avg_utilization,
    SUM(calls_handled) as total_calls
FROM v_agent_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY department
ORDER BY avg_occupancy DESC;

-- 4. Agent availability chart (login patterns)
SELECT 
    agent_name,
    metric_date,
    login_time,
    logout_time,
    shift_duration_formatted
FROM v_agent_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '7 days'
  AND login_time IS NOT NULL
ORDER BY metric_date DESC, login_time;

-- 5. Performance distribution
SELECT 
    performance_rating,
    COUNT(*) as agent_count,
    AVG(utilization_rate_pct) as avg_utilization,
    AVG(calls_handled) as avg_calls
FROM v_agent_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY performance_rating
ORDER BY 
    CASE performance_rating
        WHEN 'Excellent' THEN 1
        WHEN 'Good' THEN 2
        WHEN 'Fair' THEN 3
        WHEN 'Needs Improvement' THEN 4
        ELSE 5
    END;

*/
