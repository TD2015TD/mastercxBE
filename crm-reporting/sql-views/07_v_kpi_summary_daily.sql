-- ========================================
-- v_kpi_summary_daily
-- ========================================
-- Data sources:
--   - tickets (CRM ticket volume, SLA, CSAT)
--   - mitel_data_agent_events (ACD call volume, talk time, ACW, shift/login time)
--   - mitel_agent_inbound_call_details (time-to-answer, for ASA)
--
-- IMPORTANT — data availability notes (2026-07-26):
--   - Queue-level Offered/Answered/Abandoned/Service Level (mitel_data_queue_performance) are
--     NOT included. Verified directly against the live Mitel source: every metric column is 0
--     across all 8,216 rows, all dates — Mitel has never recorded this data. Not a mapping bug,
--     not fixable from our side. Do not resurrect abandon/service-level columns from that table.
--   - has_call_data flags whether the day actually has synced Mitel call data, vs genuinely zero
--     activity, to avoid implying false precision.

CREATE OR REPLACE VIEW v_kpi_summary_daily AS
WITH daily_call_metrics AS (
    SELECT
        mdae.event_date::date AS metric_date,
        SUM(mdae.acd_count) as total_calls,
        SUM(mdae.acd_duration) as acd_duration_sec,
        SUM(mdae.wrap_up_duration) as acw_duration_sec,
        SUM(mdae.shift_duration) as shift_duration_sec,
        COUNT(DISTINCT a.id) as active_agents
    FROM mitel_data_agent_events mdae
    JOIN mitel_config_agents mca ON LOWER(mdae.agent_id) = LOWER(mca.fk_master_agent)
    JOIN agents a ON LOWER(mca.fk_employee) = LOWER(a.employee_id) AND a.deleted_at IS NULL
    WHERE mdae.event_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY mdae.event_date::date
),

daily_asa AS (
    SELECT
        maicd.date_of_call::date AS metric_date,
        AVG(maicd.time_to_answer) as avg_speed_of_answer_sec
    FROM mitel_agent_inbound_call_details maicd
    WHERE maicd.time_to_answer IS NOT NULL
      AND maicd.date_of_call >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY maicd.date_of_call::date
),

daily_abandon AS (
    -- Same availability caveat as the agent-level view: mapping is in place, source is
    -- currently returning zero rows at the Mitel end.
    SELECT
        mapbp.midnight_start_date::date AS metric_date,
        SUM(mapbp.agent_acd_count) as acd_calls,
        SUM(mapbp.agent_abandoned_count) as abandoned_calls
    FROM mitel_agent_performance_by_period mapbp
    WHERE mapbp.midnight_start_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY mapbp.midnight_start_date::date
),

daily_ticket_metrics AS (
    SELECT
        DATE(t.created_at) AS metric_date,

        COUNT(*) AS total_tickets,
        COUNT(CASE WHEN t.status IN ('Resolved', 'Closed') THEN 1 END) AS resolved_tickets,
        COUNT(CASE WHEN t.priority = 'P1' THEN 1 END) AS critical_tickets,
        COUNT(*) FILTER (WHERE t.resolved_at IS NOT NULL) as tickets_with_resolution,
        COUNT(*) FILTER (WHERE t.resolved_at IS NOT NULL AND t.reopened_count = 0) as fcr_tickets,

        MODE() WITHIN GROUP (ORDER BY t.category) AS top_issue_category,

        AVG(t.customer_rating) AS avg_csat_score

    FROM public.tickets t
    WHERE t.created_at >= CURRENT_DATE - INTERVAL '90 days'
      AND t.deleted_at IS NULL
    GROUP BY DATE(t.created_at)
)

-- ==============================
-- FINAL KPI VIEW
-- ==============================
SELECT
    COALESCE(dcm.metric_date, dtm.metric_date) AS date,

    EXTRACT(YEAR  FROM COALESCE(dcm.metric_date, dtm.metric_date))::int AS year,
    EXTRACT(MONTH FROM COALESCE(dcm.metric_date, dtm.metric_date))::int AS month,
    EXTRACT(DAY   FROM COALESCE(dcm.metric_date, dtm.metric_date))::int AS day,
    EXTRACT(DOW   FROM COALESCE(dcm.metric_date, dtm.metric_date))::int AS day_of_week,

    CASE EXTRACT(DOW FROM COALESCE(dcm.metric_date, dtm.metric_date))::int
        WHEN 0 THEN 'Sunday'
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
    END AS day_name,

    -- ==============================
    -- CALL KPIs (Mitel-sourced)
    -- ==============================
    COALESCE(dcm.total_calls, 0) AS total_calls,
    (dcm.metric_date IS NOT NULL) as has_call_data,

    CASE WHEN dcm.total_calls > 0
         THEN ROUND((dcm.acd_duration_sec + dcm.acw_duration_sec)::numeric / dcm.total_calls / 60.0, 2)
         ELSE NULL END AS avg_handle_time_min,

    ROUND(dasa.avg_speed_of_answer_sec, 2) AS avg_speed_of_answer_sec,

    CASE WHEN dcm.shift_duration_sec > 0
         THEN ROUND((dcm.acd_duration_sec + dcm.acw_duration_sec)::numeric * 100.0 / dcm.shift_duration_sec, 2)
         ELSE NULL END AS occupancy_rate_pct,

    COALESCE(dcm.active_agents, 0) AS active_agents_with_calls,

    (dab.metric_date IS NOT NULL) as has_abandon_data,
    COALESCE(dab.abandoned_calls, 0) as abandoned_calls,
    CASE WHEN (dab.acd_calls + dab.abandoned_calls) > 0
         THEN ROUND(dab.abandoned_calls::numeric * 100.0 / (dab.acd_calls + dab.abandoned_calls), 2)
         ELSE NULL END AS abandon_rate_pct,

    -- ==============================
    -- TICKET KPIs (CRM-sourced)
    -- ==============================
    COALESCE(dtm.top_issue_category, 'N/A') AS top_issue_category,
    COALESCE(dtm.total_tickets, 0) AS total_tickets,
    COALESCE(dtm.resolved_tickets, 0) AS resolved_tickets,
    COALESCE(dtm.critical_tickets, 0) AS critical_tickets,

    ROUND(COALESCE(dtm.avg_csat_score, 0), 2) AS avg_csat_score,

    -- First Contact Resolution — channel-agnostic, from CRM ticket reopens
    COALESCE(dtm.tickets_with_resolution, 0) AS tickets_with_resolution,
    COALESCE(dtm.fcr_tickets, 0) AS fcr_tickets,
    CASE WHEN dtm.tickets_with_resolution > 0
         THEN ROUND(dtm.fcr_tickets::numeric * 100.0 / dtm.tickets_with_resolution, 2)
         ELSE NULL END AS fcr_rate_pct

FROM daily_call_metrics dcm
FULL OUTER JOIN daily_ticket_metrics dtm
    ON dcm.metric_date = dtm.metric_date
LEFT JOIN daily_asa dasa
    ON dasa.metric_date = COALESCE(dcm.metric_date, dtm.metric_date)
LEFT JOIN daily_abandon dab
    ON dab.metric_date = COALESCE(dcm.metric_date, dtm.metric_date)

WHERE COALESCE(dcm.metric_date, dtm.metric_date) >= CURRENT_DATE - INTERVAL '90 days'

ORDER BY date DESC;
