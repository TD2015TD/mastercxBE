-- ========================================
-- v_agent_kpi_summary
-- Executive KPI Dashboard for Agent Performance
-- ========================================
-- Purpose: High-level agent performance KPIs with trends and benchmarks
-- Data Sources:
--   - mitel_data_agent_events (ACD volume, talk time, ACW/wrap-up time, shift/login time)
--   - mitel_agent_inbound_call_details (time-to-answer, for ASA)
--   - tickets (FCR — first contact resolution, channel-agnostic)
-- Use Cases:
--   - Executive dashboards
--   - Team performance comparison
--   - Trend analysis (WoW, MoM)
--   - Agent ranking and benchmarking
--
-- IMPORTANT — data availability notes (2026-07-26):
--   - Abandon Rate / Short Abandon / Transfer Rate / internal-external split are NOT included.
--     Their only possible source (mitel_agent_performance_by_period / AgentPerformanceByPeriodStats)
--     has these fields permanently at 0 at the Mitel source — not a mapping bug, the data was
--     simply never recorded there. Do not resurrect those columns without a real upstream source.
--   - has_call_data flags whether an agent/week actually has synced Mitel event data, vs. simply
--     having zero activity — avoid treating NULL/0 rows as "0% abandonment" style false precision.

DROP VIEW IF EXISTS v_agent_kpi_summary CASCADE;

CREATE VIEW v_agent_kpi_summary AS
WITH weekly_call_aggregates AS (
    SELECT
        DATE_TRUNC('week', mdae.event_date)::date as week_start,
        a.id as crm_agent_id,
        a.employee_id,

        SUM(mdae.acd_count) as acd_calls,
        SUM(mdae.acd_duration) as acd_duration_sec,
        SUM(mdae.wrap_up_duration) as acw_duration_sec,
        SUM(mdae.shift_duration) as shift_duration_sec,
        SUM(mdae.make_busy_duration) as make_busy_duration_sec

    FROM mitel_data_agent_events mdae
    JOIN mitel_config_agents mca ON LOWER(mdae.agent_id) = LOWER(mca.fk_master_agent)
    JOIN agents a ON LOWER(mca.fk_employee) = LOWER(a.employee_id) AND a.deleted_at IS NULL
    GROUP BY DATE_TRUNC('week', mdae.event_date)::date, a.id, a.employee_id
),

weekly_asa AS (
    -- Average Speed of Answer, from call-detail time-to-answer records
    SELECT
        DATE_TRUNC('week', maicd.date_of_call)::date as week_start,
        a.id as crm_agent_id,
        AVG(maicd.time_to_answer) as avg_speed_of_answer_sec
    FROM mitel_agent_inbound_call_details maicd
    JOIN mitel_config_agents mca ON LOWER(maicd.agent_id::text) = LOWER(mca.fk_master_agent::text)
    JOIN agents a ON LOWER(mca.fk_employee) = LOWER(a.employee_id) AND a.deleted_at IS NULL
    WHERE maicd.time_to_answer IS NOT NULL
    GROUP BY DATE_TRUNC('week', maicd.date_of_call)::date, a.id
),

weekly_abandon AS (
    -- Abandon Rate. Source (AgentPerformanceByPeriodStats) is currently returning zero rows
    -- at the Mitel end — mapping is in place and will populate automatically once Mitel's
    -- reporting pipeline resumes writing to it. has_abandon_data distinguishes "no data synced"
    -- from a genuine 0%.
    SELECT
        DATE_TRUNC('week', mapbp.midnight_start_date)::date as week_start,
        a.id as crm_agent_id,
        SUM(mapbp.agent_acd_count) as acd_calls,
        SUM(mapbp.agent_abandoned_count) as abandoned_calls
    FROM mitel_agent_performance_by_period mapbp
    JOIN agents a ON LOWER(mapbp.employee_id) = LOWER(a.employee_id) AND a.deleted_at IS NULL
    GROUP BY DATE_TRUNC('week', mapbp.midnight_start_date)::date, a.id
),

weekly_fcr AS (
    -- First Contact Resolution: resolved/closed tickets that were never reopened.
    -- Channel-agnostic (CRM-sourced), independent of Mitel data availability.
    SELECT
        DATE_TRUNC('week', t.resolved_at)::date as week_start,
        t.assigned_to as crm_agent_id,
        COUNT(*) as resolved_tickets,
        COUNT(*) FILTER (WHERE t.reopened_count = 0) as fcr_tickets
    FROM tickets t
    WHERE t.resolved_at IS NOT NULL
      AND t.deleted_at IS NULL
    GROUP BY DATE_TRUNC('week', t.resolved_at)::date, t.assigned_to
),

monthly_call_aggregates AS (
    SELECT
        DATE_TRUNC('month', mdae.event_date)::date as month_start,
        a.employee_id,
        SUM(mdae.acd_count) as acd_calls,
        SUM(mdae.acd_duration) as acd_duration_sec,
        SUM(mdae.shift_duration) as shift_duration_sec
    FROM mitel_data_agent_events mdae
    JOIN mitel_config_agents mca ON LOWER(mdae.agent_id) = LOWER(mca.fk_master_agent)
    JOIN agents a ON LOWER(mca.fk_employee) = LOWER(a.employee_id) AND a.deleted_at IS NULL
    GROUP BY DATE_TRUNC('month', mdae.event_date)::date, a.employee_id
),

current_month AS (
    SELECT employee_id,
           acd_calls as current_month_acd_calls,
           shift_duration_sec as current_month_shift_duration
    FROM monthly_call_aggregates
    WHERE month_start = DATE_TRUNC('month', CURRENT_DATE)::date
),

previous_month AS (
    SELECT employee_id,
           acd_calls as previous_month_acd_calls,
           shift_duration_sec as previous_month_shift_duration
    FROM monthly_call_aggregates
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

    -- === CALL VOLUME ===
    COALESCE(wa.acd_calls, 0) as total_calls,
    (wa.acd_calls IS NOT NULL) as has_call_data,

    -- === TIME EFFICIENCY KPIs ===
    ROUND(COALESCE(wa.acd_duration_sec, 0) / 3600.0, 2) as acd_talk_hours,
    ROUND(COALESCE(wa.acw_duration_sec, 0) / 3600.0, 2) as acw_hours,
    ROUND(COALESCE(wa.shift_duration_sec, 0) / 3600.0, 2) as shift_hours,

    -- Average Handle Time (AHT) = (talk time + wrap-up time) / calls handled
    CASE WHEN wa.acd_calls > 0
         THEN ROUND((wa.acd_duration_sec + wa.acw_duration_sec)::numeric / wa.acd_calls / 60.0, 2)
         ELSE NULL END as avg_handle_time_min,

    -- Average Talk Time
    CASE WHEN wa.acd_calls > 0
         THEN ROUND(wa.acd_duration_sec::numeric / wa.acd_calls / 60.0, 2)
         ELSE NULL END as avg_talk_time_min,

    -- Average ACW (wrap-up time per call)
    CASE WHEN wa.acd_calls > 0
         THEN ROUND(wa.acw_duration_sec::numeric / wa.acd_calls, 2)
         ELSE NULL END as avg_acw_sec,

    -- Average Speed of Answer (ASA)
    ROUND(asa.avg_speed_of_answer_sec, 2) as avg_speed_of_answer_sec,

    -- === PRODUCTIVITY / UTILISATION ===
    -- Occupancy = (talk + wrap-up time) / shift (logged-in) time
    CASE WHEN wa.shift_duration_sec > 0
         THEN ROUND((wa.acd_duration_sec + wa.acw_duration_sec)::numeric * 100.0 / wa.shift_duration_sec, 2)
         ELSE NULL END as occupancy_rate_pct,

    CASE WHEN wa.shift_duration_sec > 0
         THEN ROUND(wa.acd_calls::numeric * 3600.0 / wa.shift_duration_sec, 2)
         ELSE NULL END as calls_per_hour,

    -- === ABANDON RATE — see weekly_abandon CTE note on data availability ===
    (ab.crm_agent_id IS NOT NULL) as has_abandon_data,
    COALESCE(ab.abandoned_calls, 0) as abandoned_calls,
    CASE WHEN (ab.acd_calls + ab.abandoned_calls) > 0
         THEN ROUND(ab.abandoned_calls::numeric * 100.0 / (ab.acd_calls + ab.abandoned_calls), 2)
         ELSE NULL END as abandon_rate_pct,

    -- === FIRST CONTACT RESOLUTION (FCR) — from CRM ticket reopens, not Mitel ===
    COALESCE(fcr.resolved_tickets, 0) as resolved_tickets,
    COALESCE(fcr.fcr_tickets, 0) as fcr_tickets,
    CASE WHEN fcr.resolved_tickets > 0
         THEN ROUND(fcr.fcr_tickets::numeric * 100.0 / fcr.resolved_tickets, 2)
         ELSE NULL END as fcr_rate_pct,

    -- === MONTH-OVER-MONTH TRENDS ===
    cm.current_month_acd_calls,
    pm.previous_month_acd_calls,
    CASE WHEN pm.previous_month_acd_calls > 0
         THEN ROUND((cm.current_month_acd_calls - pm.previous_month_acd_calls)::numeric * 100.0 / pm.previous_month_acd_calls, 2)
         ELSE NULL END as acd_calls_mom_change_pct,

    -- === PERFORMANCE INDICATORS ===
    CASE
        WHEN wa.shift_duration_sec > 0 AND
             (wa.acd_duration_sec + wa.acw_duration_sec)::numeric * 100.0 / wa.shift_duration_sec >= 85
        THEN 'High'
        WHEN wa.shift_duration_sec > 0 AND
             (wa.acd_duration_sec + wa.acw_duration_sec)::numeric * 100.0 / wa.shift_duration_sec >= 70
        THEN 'Medium'
        WHEN wa.shift_duration_sec > 0
        THEN 'Low'
        ELSE 'No Data'
    END as occupancy_tier,

    -- === STATUS ===
    CASE
        WHEN wa.week_start = DATE_TRUNC('week', CURRENT_DATE)::date
        THEN 'Current Week'
        WHEN wa.week_start = DATE_TRUNC('week', CURRENT_DATE - INTERVAL '1 week')::date
        THEN 'Last Week'
        ELSE 'Historical'
    END as week_status

FROM weekly_call_aggregates wa
JOIN agents a ON a.id = wa.crm_agent_id
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN weekly_asa asa ON asa.crm_agent_id = wa.crm_agent_id AND asa.week_start = wa.week_start
LEFT JOIN weekly_abandon ab ON ab.crm_agent_id = wa.crm_agent_id AND ab.week_start = wa.week_start
LEFT JOIN weekly_fcr fcr ON fcr.crm_agent_id = wa.crm_agent_id AND fcr.week_start = wa.week_start
LEFT JOIN current_month cm ON wa.employee_id = cm.employee_id
LEFT JOIN previous_month pm ON wa.employee_id = pm.employee_id
;
