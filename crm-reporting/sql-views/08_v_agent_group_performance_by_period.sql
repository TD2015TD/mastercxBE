-- ========================================
-- v_agent_group_performance_by_period
-- Agent Group Performance by Period Report
-- ========================================
-- Purpose: Flattened view of agent group performance metrics by time period
-- Data Source: Mitel AgentPerformanceByPeriodStats and AgentEventStats
-- Use Cases:
--   - Agent group performance analysis by hour/day/week/month
--   - ACD vs Non-ACD call handling comparison
--   - Outbound call performance tracking
--   - Transfer and conference call monitoring
--   - Agent staffing and manned time analysis
-- Optimized for: Apache Superset, PowerBI, Tableau

DROP VIEW IF EXISTS v_agent_group_performance_by_period CASCADE;

CREATE VIEW v_agent_group_performance_by_period AS
WITH calls_base AS (
    -- Use v_call_interaction as the source of truth
    SELECT
        call_start_time,
        call_date,
        call_year,
        call_month,
        call_day,
        call_hour,
        day_of_week,
        day_name,
        direction,
        call_status,
        queue_name,
        COALESCE(agent_department, 'UNASSIGNED') as agent_group_id,
        COALESCE(agent_department, 'UNASSIGNED') as agent_group_name,
        agent_id,
        total_handle_time_sec as call_duration_sec,
        -- Classify call types
        CASE
            WHEN direction = 'Inbound' AND call_status = 'Answered' AND queue_name IS NOT NULL THEN 'ACD'
            WHEN direction = 'Inbound' AND call_status = 'Answered' AND queue_name IS NULL THEN 'Non-ACD'
            WHEN direction = 'Internal' AND call_status = 'Answered' THEN 'Non-ACD'
            WHEN direction = 'Inbound' AND call_status = 'Abandoned' THEN 'Abandoned'
            WHEN direction = 'Outbound' AND call_status = 'Answered' THEN 'Outbound'
            ELSE 'Other'
        END as call_type_category
    FROM v_call_interaction
    WHERE call_status IN ('Answered', 'Abandoned')
),

time_periods AS (
    -- Generate time periods for aggregation by hour
    SELECT DISTINCT
        DATE_TRUNC('hour', call_start_time) as period_start,
        DATE_TRUNC('hour', call_start_time) + INTERVAL '1 hour' as period_end,
        call_date as period_date,
        call_year as period_year,
        call_month as period_month,
        call_day as period_day,
        call_hour as period_hour,
        day_of_week,
        day_name
    FROM calls_base
),

agent_groups AS (
    -- Get distinct agent groups (departments)
    SELECT DISTINCT
        agent_group_id,
        agent_group_name
    FROM calls_base
),

agent_group_periods AS (
    -- Cross join agent groups with time periods
    SELECT
        ag.agent_group_id,
        ag.agent_group_name,
        ag.agent_group_id as agent_group_reporting_id,
        tp.period_start,
        tp.period_end,
        tp.period_date,
        tp.period_year,
        tp.period_month,
        tp.period_day,
        tp.period_hour,
        tp.day_of_week,
        tp.day_name
    FROM agent_groups ag
    CROSS JOIN time_periods tp
),

acd_calls_summary AS (
    -- ACD (Inbound Queue) Calls - Answered
    SELECT
        agent_group_id,
        DATE_TRUNC('hour', call_start_time) as period_start,
        COUNT(*) as acd_calls_handled,
        SUM(COALESCE(call_duration_sec, 0)) as total_acd_handling_time_sec,
        AVG(COALESCE(call_duration_sec, 0)) as avg_acd_handling_time_sec
    FROM calls_base
    WHERE call_type_category = 'ACD'
    GROUP BY agent_group_id, DATE_TRUNC('hour', call_start_time)
),

non_acd_calls_summary AS (
    -- Non-ACD (Internal/Direct) Calls - Answered
    SELECT
        agent_group_id,
        DATE_TRUNC('hour', call_start_time) as period_start,
        COUNT(*) as non_acd_calls_handled,
        SUM(COALESCE(call_duration_sec, 0)) as total_non_acd_handling_time_sec,
        AVG(COALESCE(call_duration_sec, 0)) as avg_non_acd_handling_time_sec
    FROM calls_base
    WHERE call_type_category = 'Non-ACD'
    GROUP BY agent_group_id, DATE_TRUNC('hour', call_start_time)
),

abandoned_calls_summary AS (
    -- Abandoned Calls
    SELECT
        agent_group_id,
        DATE_TRUNC('hour', call_start_time) as period_start,
        COUNT(*) as calls_abandoned
    FROM calls_base
    WHERE call_type_category = 'Abandoned'
    GROUP BY agent_group_id, DATE_TRUNC('hour', call_start_time)
),

outbound_calls_summary AS (
    -- Outbound Calls
    SELECT
        agent_group_id,
        DATE_TRUNC('hour', call_start_time) as period_start,
        COUNT(*) as calls_outbound,
        SUM(COALESCE(call_duration_sec, 0)) as total_outbound_handling_time_sec,
        AVG(COALESCE(call_duration_sec, 0)) as avg_outbound_handling_time_sec
    FROM calls_base
    WHERE call_type_category = 'Outbound'
    GROUP BY agent_group_id, DATE_TRUNC('hour', call_start_time)
)

-- === MAIN QUERY ===
SELECT
    -- === IDENTIFIERS ===
    agp.agent_group_id,
    agp.agent_group_name,
    agp.agent_group_reporting_id,
    
    -- === TIME PERIOD ===
    agp.period_start as activity_period_start,
    agp.period_end as activity_period_end,
    TO_CHAR(agp.period_start, 'YYYY-MM-DD HH24:MI') as activity_period,
    -- Human-readable label: Monday, 2026-01-27 02:00 PM - 03:00 PM
    agp.day_name || ', ' || TO_CHAR(agp.period_start, 'YYYY-MM-DD HH12:MI AM') || ' - ' || TO_CHAR(agp.period_end, 'HH12:MI AM') as activity_period_label,
    agp.period_date,
    agp.period_year,
    agp.period_month,
    agp.period_day,
    agp.period_hour,
    agp.day_of_week,
    agp.day_name,
    
    -- === CALL VOLUMES ===
    COALESCE(acd.acd_calls_handled, 0) as acd_calls_handled,
    COALESCE(nacd.non_acd_calls_handled, 0) as non_acd_calls_handled,
    COALESCE(abd.calls_abandoned, 0) as calls_abandoned,
    COALESCE(out.calls_outbound, 0) as calls_outbound,
    
    -- === TRANSFER & ROUTING METRICS ===
    -- Note: These metrics require additional data sources not currently available
    -- in mitel_agent_inbound_call_details or mitel_agent_outbound_call_details
    0 as calls_requeued,
    0 as calls_transferred_to_agent,
    0 as calls_transferred_from_agent,
    0 as conference_calls,
    0 as account_code_calls,
    
    -- === ACD HANDLING TIME ===
    COALESCE(acd.total_acd_handling_time_sec, 0) as acd_handling_time_sec,
    COALESCE(acd.avg_acd_handling_time_sec, 0) as avg_acd_handling_time_sec,
    
    -- Convert to HH:MM:SS format
    TO_CHAR(COALESCE(acd.total_acd_handling_time_sec, 0) * INTERVAL '1 second', 'HH24:MI:SS') as acd_handling_time_hhmmss,
    TO_CHAR(COALESCE(acd.avg_acd_handling_time_sec, 0) * INTERVAL '1 second', 'HH24:MI:SS') as avg_acd_handling_time_hhmmss,
    
    -- === NON-ACD HANDLING TIME ===
    COALESCE(nacd.total_non_acd_handling_time_sec, 0) as non_acd_handling_time_sec,
    COALESCE(nacd.avg_non_acd_handling_time_sec, 0) as avg_non_acd_handling_time_sec,
    
    -- Convert to HH:MM:SS format
    TO_CHAR(COALESCE(nacd.total_non_acd_handling_time_sec, 0) * INTERVAL '1 second', 'HH24:MI:SS') as non_acd_handling_time_hhmmss,
    TO_CHAR(COALESCE(nacd.avg_non_acd_handling_time_sec, 0) * INTERVAL '1 second', 'HH24:MI:SS') as avg_non_acd_handling_time_hhmmss,
    
    -- === OUTBOUND HANDLING TIME ===
    COALESCE(out.total_outbound_handling_time_sec, 0) as outbound_handling_time_sec,
    COALESCE(out.avg_outbound_handling_time_sec, 0) as avg_outbound_handling_time_sec,
    
    -- Convert to HH:MM:SS format
    TO_CHAR(COALESCE(out.total_outbound_handling_time_sec, 0) * INTERVAL '1 second', 'HH24:MI:SS') as outbound_handling_time_hhmmss,
    TO_CHAR(COALESCE(out.avg_outbound_handling_time_sec, 0) * INTERVAL '1 second', 'HH24:MI:SS') as avg_outbound_handling_time_hhmmss,
    
    -- === STAFFING METRICS ===
    -- Average Manned: Count unique agents who handled calls in the period
    (
        SELECT COUNT(DISTINCT agent_id)
        FROM calls_base cb
        WHERE cb.agent_group_id = agp.agent_group_id
          AND DATE_TRUNC('hour', cb.call_start_time) = agp.period_start
          AND cb.call_type_category IN ('ACD', 'Non-ACD', 'Outbound')
    ) as avg_manned_agents,
    
    -- === TOTAL CALL VOLUME ===
    COALESCE(acd.acd_calls_handled, 0) + 
    COALESCE(nacd.non_acd_calls_handled, 0) + 
    COALESCE(out.calls_outbound, 0) as total_calls_handled,
    
    -- === TOTAL HANDLING TIME ===
    COALESCE(acd.total_acd_handling_time_sec, 0) + 
    COALESCE(nacd.total_non_acd_handling_time_sec, 0) + 
    COALESCE(out.total_outbound_handling_time_sec, 0) as total_handling_time_sec,
    
    TO_CHAR(
        (COALESCE(acd.total_acd_handling_time_sec, 0) + 
         COALESCE(nacd.total_non_acd_handling_time_sec, 0) + 
         COALESCE(out.total_outbound_handling_time_sec, 0)) * INTERVAL '1 second', 
        'HH24:MI:SS'
    ) as total_handling_time_hhmmss,
    
    -- === METADATA ===
    CURRENT_TIMESTAMP as record_generated_at

FROM agent_group_periods agp
LEFT JOIN acd_calls_summary acd 
    ON agp.agent_group_id = acd.agent_group_id 
    AND agp.period_start = acd.period_start
LEFT JOIN non_acd_calls_summary nacd 
    ON agp.agent_group_id = nacd.agent_group_id 
    AND agp.period_start = nacd.period_start
LEFT JOIN abandoned_calls_summary abd 
    ON agp.agent_group_id = abd.agent_group_id 
    AND agp.period_start = abd.period_start
LEFT JOIN outbound_calls_summary out 
    ON agp.agent_group_id = out.agent_group_id 
    AND agp.period_start = out.period_start

-- Filter to only include periods where there was at least some activity
WHERE COALESCE(acd.acd_calls_handled, 0) + 
      COALESCE(nacd.non_acd_calls_handled, 0) + 
      COALESCE(abd.calls_abandoned, 0) + 
      COALESCE(out.calls_outbound, 0) > 0

ORDER BY agp.agent_group_id, agp.period_start;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Agent Group Performance by Hour (Today)
SELECT 
    agent_group_name,
    activity_period,
    acd_calls_handled,
    non_acd_calls_handled,
    calls_outbound,
    calls_abandoned,
    acd_handling_time_hhmmss,
    avg_acd_handling_time_hhmmss,
    avg_manned_agents
FROM v_agent_group_performance_by_period
WHERE period_date = CURRENT_DATE
ORDER BY agent_group_name, period_hour;

-- 2. Daily Summary by Agent Group (Last 30 Days)
SELECT 
    agent_group_name,
    period_date,
    SUM(acd_calls_handled) as total_acd_calls,
    SUM(non_acd_calls_handled) as total_non_acd_calls,
    SUM(calls_outbound) as total_outbound,
    SUM(calls_abandoned) as total_abandoned,
    TO_CHAR(SUM(acd_handling_time_sec) * INTERVAL '1 second', 'HH24:MI:SS') as total_acd_time,
    ROUND(AVG(avg_acd_handling_time_sec), 2) as avg_acd_time_sec,
    ROUND(AVG(avg_manned_agents), 2) as avg_agents
FROM v_agent_group_performance_by_period
WHERE period_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY agent_group_name, period_date
ORDER BY period_date DESC, agent_group_name;

-- 3. Peak Hour Analysis by Agent Group
SELECT 
    agent_group_name,
    period_hour,
    day_name,
    ROUND(AVG(acd_calls_handled), 2) as avg_acd_calls,
    ROUND(AVG(calls_outbound), 2) as avg_outbound_calls,
    ROUND(AVG(avg_manned_agents), 2) as avg_agents,
    TO_CHAR(AVG(avg_acd_handling_time_sec) * INTERVAL '1 second', 'HH24:MI:SS') as avg_handle_time
FROM v_agent_group_performance_by_period
WHERE period_date >= CURRENT_DATE - INTERVAL '90 days'
GROUP BY agent_group_name, period_hour, day_name
ORDER BY agent_group_name, period_hour;

-- 4. Agent Group Comparison (Last 7 Days)
SELECT 
    agent_group_name,
    SUM(acd_calls_handled) as total_acd_calls,
    SUM(non_acd_calls_handled) as total_non_acd_calls,
    SUM(calls_outbound) as total_outbound,
    SUM(calls_abandoned) as total_abandoned,
    ROUND(AVG(avg_acd_handling_time_sec), 2) as avg_acd_handle_sec,
    ROUND(AVG(avg_manned_agents), 2) as avg_staffing,
    ROUND(
        SUM(calls_abandoned)::numeric * 100.0 / 
        NULLIF(SUM(acd_calls_handled) + SUM(calls_abandoned), 0), 
        2
    ) as abandon_rate_pct
FROM v_agent_group_performance_by_period
WHERE period_date >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY agent_group_name
ORDER BY total_acd_calls DESC;

-- 5. Hourly Trend Analysis - Compare This Week vs Last Week
SELECT 
    agent_group_name,
    period_hour,
    SUM(CASE WHEN period_date >= CURRENT_DATE - INTERVAL '7 days' 
             THEN acd_calls_handled ELSE 0 END) as this_week_calls,
    SUM(CASE WHEN period_date < CURRENT_DATE - INTERVAL '7 days' 
             AND period_date >= CURRENT_DATE - INTERVAL '14 days'
             THEN acd_calls_handled ELSE 0 END) as last_week_calls,
    ROUND(
        AVG(CASE WHEN period_date >= CURRENT_DATE - INTERVAL '7 days' 
                 THEN avg_acd_handling_time_sec END), 
        2
    ) as this_week_avg_handle_sec,
    ROUND(
        AVG(CASE WHEN period_date < CURRENT_DATE - INTERVAL '7 days' 
                 AND period_date >= CURRENT_DATE - INTERVAL '14 days'
                 THEN avg_acd_handling_time_sec END), 
        2
    ) as last_week_avg_handle_sec
FROM v_agent_group_performance_by_period
WHERE period_date >= CURRENT_DATE - INTERVAL '14 days'
GROUP BY agent_group_name, period_hour
ORDER BY agent_group_name, period_hour;

-- 6. Monthly Performance Summary
SELECT 
    agent_group_name,
    period_year,
    period_month,
    TO_CHAR(TO_DATE(period_year::text || '-' || period_month::text || '-01', 'YYYY-MM-DD'), 'Month YYYY') as month_name,
    SUM(acd_calls_handled) as total_acd_calls,
    SUM(non_acd_calls_handled) as total_non_acd_calls,
    SUM(calls_outbound) as total_outbound,
    TO_CHAR(SUM(total_handling_time_sec) * INTERVAL '1 second', 'HH24:MI:SS') as total_handling_time,
    ROUND(AVG(avg_acd_handling_time_sec), 2) as avg_acd_handle_sec,
    ROUND(AVG(avg_manned_agents), 2) as avg_staffing
FROM v_agent_group_performance_by_period
GROUP BY agent_group_name, period_year, period_month
ORDER BY period_year DESC, period_month DESC, agent_group_name;

*/

-- === NOTES FOR FUTURE ENHANCEMENTS ===
/*
1. Calls Requeued: Requires call transfer/requeue event data from Mitel
   - Potential source: tblData_AgentTrace or custom requeue tracking

2. Calls Transferred (To/From Agent): Requires call transfer event data
   - Potential source: Mitel transfer events or call disposition metadata

3. Conference Calls: Requires conference call event data
   - Potential source: Mitel conference tracking or multi-party call data

4. Account Code: Requires account code usage tracking
   - Potential source: tblData_AgentByAccountCode table

5. Average Manned: Currently calculated as count of unique agents with calls
   - Better source: Agent state/presence data showing logged-in duration
   - Potential source: mitel_data_agent_trace_events or agent state logs

6. Agent Group Membership: Currently using queue_id as proxy
   - Better source: tblConfig_AgentGroupMembers mapping table
   - Should be added to sync configuration
*/
