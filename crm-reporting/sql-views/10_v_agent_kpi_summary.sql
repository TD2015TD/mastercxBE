-- ========================================
-- v_agent_kpi_summary
-- Executive KPI Dashboard for Agent Performance
-- ========================================
-- Purpose: High-level agent performance KPIs with trends and benchmarks
-- Data Source: mitel_agent_performance_by_period
-- Use Cases:
--   - Executive dashboards
--   - Team performance comparison
--   - SLA monitoring
--   - Trend analysis (WoW, MoM)
--   - Agent ranking and benchmarking
-- Optimized for: Apache Superset executive views

DROP VIEW IF EXISTS v_agent_kpi_summary CASCADE;

CREATE VIEW v_agent_kpi_summary AS
WITH weekly_aggregates AS (
    SELECT
        DATE_TRUNC('week', mapbp.midnight_start_date)::date as week_start,
        mapbp.employee_id,
        
        -- Call volumes
        SUM(mapbp.agent_acd_count) as acd_calls,
        SUM(mapbp.agent_non_acd_count) as non_acd_calls,
        SUM(mapbp.agent_outbound_count) as outbound_calls,
        SUM(mapbp.agent_abandoned_count) as abandoned_calls,
        
        -- Time metrics
        SUM(mapbp.agent_acd_duration) as acd_duration_sec,
        SUM(mapbp.agent_acw_duration) as acw_duration_sec,
        SUM(mapbp.agent_login_duration) as login_duration_sec,
        
        -- Transfer metrics
        SUM(mapbp.agent_transfer_count) as transfers,
        
        -- Internal/External
        SUM(mapbp.agent_acd_external_count + mapbp.agent_non_acd_external_count + mapbp.agent_outbound_external_count) as external_calls,
        SUM(mapbp.agent_acd_internal_count + mapbp.agent_non_acd_internal_count + mapbp.agent_outbound_internal_count) as internal_calls
        
    FROM mitel_agent_performance_by_period mapbp
    GROUP BY DATE_TRUNC('week', mapbp.midnight_start_date)::date, mapbp.employee_id
),

monthly_aggregates AS (
    SELECT
        DATE_TRUNC('month', mapbp.midnight_start_date)::date as month_start,
        mapbp.employee_id,
        
        -- Call volumes
        SUM(mapbp.agent_acd_count) as acd_calls,
        SUM(mapbp.agent_non_acd_count) as non_acd_calls,
        SUM(mapbp.agent_outbound_count) as outbound_calls,
        
        -- Time metrics
        SUM(mapbp.agent_acd_duration) as acd_duration_sec,
        SUM(mapbp.agent_acw_duration) as acw_duration_sec,
        SUM(mapbp.agent_login_duration) as login_duration_sec
        
    FROM mitel_agent_performance_by_period mapbp
    GROUP BY DATE_TRUNC('month', mapbp.midnight_start_date)::date, mapbp.employee_id
),

current_month AS (
    SELECT 
        employee_id,
        acd_calls as current_month_acd_calls,
        acd_duration_sec as current_month_acd_duration,
        login_duration_sec as current_month_login_duration
    FROM monthly_aggregates
    WHERE month_start = DATE_TRUNC('month', CURRENT_DATE)::date
),

previous_month AS (
    SELECT 
        employee_id,
        acd_calls as previous_month_acd_calls,
        acd_duration_sec as previous_month_acd_duration,
        login_duration_sec as previous_month_login_duration
    FROM monthly_aggregates
    WHERE month_start = DATE_TRUNC('month', CURRENT_DATE - INTERVAL '1 month')::date
)

SELECT
    -- === IDENTIFIERS ===
    wa.week_start,
    EXTRACT(YEAR FROM wa.week_start)::int as year,
    EXTRACT(WEEK FROM wa.week_start)::int as week_number,
    
    -- === AGENT INFORMATION ===
    a.id as agent_id,
    wa.employee_id,
    u.name as agent_name,
    a.agent_code,
    a.department,
    a.designation,
    u.email as agent_email,
    
    -- === CALL VOLUME KPIs ===
    wa.acd_calls,
    wa.non_acd_calls,
    wa.outbound_calls,
    wa.acd_calls + wa.non_acd_calls + wa.outbound_calls as total_calls,
    wa.external_calls,
    wa.internal_calls,
    
    -- External call percentage
    CASE WHEN (wa.external_calls + wa.internal_calls) > 0 
         THEN ROUND(wa.external_calls::numeric * 100.0 / (wa.external_calls + wa.internal_calls), 2)
         ELSE 0 END as external_call_pct,
    
    -- === ABANDON RATE ===
    wa.abandoned_calls,
    CASE WHEN (wa.acd_calls + wa.abandoned_calls) > 0 
         THEN ROUND(wa.abandoned_calls::numeric * 100.0 / (wa.acd_calls + wa.abandoned_calls), 2)
         ELSE 0 END as abandon_rate_pct,
    
    -- === TRANSFER RATE ===
    wa.transfers,
    CASE WHEN wa.acd_calls > 0 
         THEN ROUND(wa.transfers::numeric * 100.0 / wa.acd_calls, 2)
         ELSE 0 END as transfer_rate_pct,
    
    -- === TIME EFFICIENCY KPIs ===
    ROUND(wa.acd_duration_sec / 3600.0, 2) as acd_talk_hours,
    ROUND(wa.acw_duration_sec / 3600.0, 2) as acw_hours,
    ROUND(wa.login_duration_sec / 3600.0, 2) as login_hours,
    
    -- Average Handle Time (AHT)
    CASE WHEN wa.acd_calls > 0 
         THEN ROUND((wa.acd_duration_sec + wa.acw_duration_sec)::numeric / wa.acd_calls / 60.0, 2)
         ELSE 0 END as avg_handle_time_min,
    
    -- Average Talk Time
    CASE WHEN wa.acd_calls > 0 
         THEN ROUND(wa.acd_duration_sec::numeric / wa.acd_calls / 60.0, 2)
         ELSE 0 END as avg_talk_time_min,
    
    -- Average ACW
    CASE WHEN wa.acd_calls > 0 
         THEN ROUND(wa.acw_duration_sec::numeric / wa.acd_calls, 2)
         ELSE 0 END as avg_acw_sec,
    
    -- === PRODUCTIVITY KPIs ===
    -- Calls per hour (logged in)
    CASE WHEN wa.login_duration_sec > 0 
         THEN ROUND((wa.acd_calls + wa.non_acd_calls + wa.outbound_calls)::numeric * 3600.0 / wa.login_duration_sec, 2)
         ELSE 0 END as calls_per_hour,
    
    -- Occupancy Rate
    CASE WHEN wa.login_duration_sec > 0 
         THEN ROUND((wa.acd_duration_sec + wa.acw_duration_sec)::numeric * 100.0 / wa.login_duration_sec, 2)
         ELSE 0 END as occupancy_rate_pct,
    
    -- === MONTH-OVER-MONTH TRENDS ===
    cm.current_month_acd_calls,
    pm.previous_month_acd_calls,
    CASE WHEN pm.previous_month_acd_calls > 0 
         THEN ROUND((cm.current_month_acd_calls - pm.previous_month_acd_calls)::numeric * 100.0 / pm.previous_month_acd_calls, 2)
         ELSE NULL END as acd_calls_mom_change_pct,
    
    CASE WHEN cm.current_month_login_duration > 0 AND pm.previous_month_login_duration > 0
         THEN ROUND(
             ((cm.current_month_acd_duration + cm.current_month_login_duration)::numeric * 100.0 / cm.current_month_login_duration) -
             ((pm.previous_month_acd_duration + pm.previous_month_login_duration)::numeric * 100.0 / pm.previous_month_login_duration),
             2)
         ELSE NULL END as occupancy_mom_change_pct,
    
    -- === PERFORMANCE INDICATORS ===
    CASE 
        WHEN wa.login_duration_sec > 0 AND 
             (wa.acd_duration_sec + wa.acw_duration_sec)::numeric * 100.0 / wa.login_duration_sec >= 85 
        THEN 'High'
        WHEN wa.login_duration_sec > 0 AND 
             (wa.acd_duration_sec + wa.acw_duration_sec)::numeric * 100.0 / wa.login_duration_sec >= 70 
        THEN 'Medium'
        WHEN wa.login_duration_sec > 0
        THEN 'Low'
        ELSE 'No Data'
    END as occupancy_tier,
    
    CASE 
        WHEN wa.acd_calls > 0 AND 
             wa.transfers::numeric * 100.0 / wa.acd_calls < 5 
        THEN 'Excellent'
        WHEN wa.acd_calls > 0 AND 
             wa.transfers::numeric * 100.0 / wa.acd_calls < 10 
        THEN 'Good'
        WHEN wa.acd_calls > 0 AND 
             wa.transfers::numeric * 100.0 / wa.acd_calls < 20 
        THEN 'Fair'
        WHEN wa.acd_calls > 0
        THEN 'Needs Improvement'
        ELSE 'No Data'
    END as transfer_performance,
    
    -- === STATUS ===
    CASE 
        WHEN wa.week_start = DATE_TRUNC('week', CURRENT_DATE)::date 
        THEN 'Current Week'
        WHEN wa.week_start = DATE_TRUNC('week', CURRENT_DATE - INTERVAL '1 week')::date 
        THEN 'Last Week'
        ELSE 'Historical'
    END as week_status

FROM weekly_aggregates wa
LEFT JOIN agents a ON LOWER(wa.employee_id) = a.employee_id AND a.deleted_at IS NULL
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN current_month cm ON wa.employee_id = cm.employee_id
LEFT JOIN previous_month pm ON wa.employee_id = pm.employee_id
WHERE wa.week_start >= CURRENT_DATE - INTERVAL '90 days' -- Last 90 days
;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Current week agent ranking by calls
SELECT 
    agent_name,
    department,
    total_calls,
    occupancy_rate_pct,
    avg_handle_time_min,
    transfer_rate_pct,
    occupancy_tier
FROM v_agent_kpi_summary
WHERE week_status = 'Current Week'
ORDER BY total_calls DESC
LIMIT 20;

-- 2. Department performance comparison
SELECT 
    department,
    COUNT(DISTINCT agent_id) as agent_count,
    SUM(total_calls) as total_calls,
    AVG(occupancy_rate_pct) as avg_occupancy,
    AVG(avg_handle_time_min) as avg_aht,
    AVG(transfer_rate_pct) as avg_transfer_rate
FROM v_agent_kpi_summary
WHERE week_status = 'Current Week'
GROUP BY department
ORDER BY total_calls DESC;

-- 3. Week-over-week trends
SELECT 
    week_start,
    SUM(total_calls) as total_calls,
    AVG(occupancy_rate_pct) as avg_occupancy,
    AVG(avg_handle_time_min) as avg_aht,
    AVG(abandon_rate_pct) as avg_abandon_rate
FROM v_agent_kpi_summary
GROUP BY week_start
ORDER BY week_start DESC
LIMIT 12;

-- 4. Top performers by occupancy
SELECT 
    agent_name,
    department,
    AVG(occupancy_rate_pct) as avg_occupancy,
    SUM(total_calls) as total_calls,
    AVG(calls_per_hour) as avg_calls_per_hour,
    transfer_performance
FROM v_agent_kpi_summary
WHERE week_start >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY agent_name, department, transfer_performance
HAVING SUM(total_calls) > 0
ORDER BY avg_occupancy DESC
LIMIT 15;

-- 5. Month-over-month growth agents
SELECT 
    agent_name,
    department,
    current_month_acd_calls,
    previous_month_acd_calls,
    acd_calls_mom_change_pct,
    occupancy_mom_change_pct
FROM v_agent_kpi_summary
WHERE week_status = 'Current Week'
  AND acd_calls_mom_change_pct IS NOT NULL
ORDER BY acd_calls_mom_change_pct DESC
LIMIT 20;

-- 6. Low performers needing attention
SELECT 
    agent_name,
    department,
    occupancy_rate_pct,
    transfer_rate_pct,
    total_calls,
    occupancy_tier,
    transfer_performance
FROM v_agent_kpi_summary
WHERE week_status = 'Current Week'
  AND (occupancy_tier = 'Low' OR transfer_performance = 'Needs Improvement')
ORDER BY occupancy_rate_pct ASC;

*/
