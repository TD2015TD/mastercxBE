CREATE OR REPLACE VIEW v_kpi_summary_daily AS
WITH daily_call_metrics AS (
    -- ==============================
    -- DAILY CALL METRICS
    -- ==============================
    SELECT
        call_date AS metric_date,

        COUNT(*) AS total_calls,
        COUNT(CASE WHEN is_answered = 1 THEN 1 END) AS answered_calls,
        COUNT(CASE WHEN is_abandoned = 1 THEN 1 END) AS abandoned_calls,
        COUNT(CASE WHEN is_missed = 1 THEN 1 END) AS missed_calls,

        AVG(CASE WHEN is_answered = 1 THEN queue_wait_time_sec END) AS avg_wait_time_sec,
        AVG(CASE WHEN is_answered = 1 THEN talk_time_sec END) AS avg_talk_time_sec,

        COUNT(CASE WHEN sla_20sec_met = 1 THEN 1 END) AS sla_met_calls,

        MODE() WITHIN GROUP (ORDER BY queue_name) AS top_queue

    FROM v_call_interaction
    WHERE call_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY call_date
),

daily_ticket_metrics AS (
    -- ==============================
    -- DAILY TICKET METRICS
    -- ==============================
    SELECT
        DATE(t.created_at) AS metric_date,

        COUNT(*) AS total_tickets,

        COUNT(CASE WHEN t.status IN ('Resolved', 'Closed') THEN 1 END) AS resolved_tickets,

        COUNT(CASE WHEN t.priority = 'P1' THEN 1 END) AS critical_tickets,

        MODE() WITHIN GROUP (ORDER BY t.category) AS top_issue_category,

        AVG(t.customer_rating) AS avg_csat_score

    FROM public.tickets t
    WHERE t.created_at >= CURRENT_DATE - INTERVAL '90 days'
      AND t.deleted_at IS NULL
    GROUP BY DATE(t.created_at)
),

daily_agent_metrics AS (
    -- ==============================
    -- DAILY AGENT UTILIZATION
    -- ==============================
    SELECT
        DATE(map.period_date) AS metric_date,

        COUNT(DISTINCT map.agent_id) AS active_agents,
        SUM(map.acd_count) AS total_acd_calls,

        AVG(
            CASE 
                WHEN map.shift_duration > 0
                THEN map.acd_duration::numeric * 100.0 / map.shift_duration
            END
        ) AS avg_utilization_pct

    FROM public.mitel_data_agent_performance map
    WHERE map.period_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY DATE(map.period_date)
),

daily_queue_metrics AS (
    -- ==============================
    -- DAILY QUEUE METRICS (SCHEMA-SAFE)
    -- ==============================
    SELECT
        DATE(mqp.period_date) AS metric_date,

        MAX(mqp.max_waiting_calls) AS max_queue_depth,
        SUM(mqp.abandon_count) AS total_queue_abandons,

        -- Service Level % (authoritative)
        AVG(mqp.service_level)::numeric AS avg_service_level_pct

    FROM public.mitel_data_queue_performance mqp
    WHERE mqp.period_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY DATE(mqp.period_date)
)

-- ==============================
-- FINAL KPI VIEW
-- ==============================
SELECT
    COALESCE(
        dcm.metric_date,
        dtm.metric_date,
        dam.metric_date,
        dqm.metric_date
    ) AS date,

    EXTRACT(YEAR  FROM COALESCE(dcm.metric_date, dtm.metric_date, dam.metric_date, dqm.metric_date))::int AS year,
    EXTRACT(MONTH FROM COALESCE(dcm.metric_date, dtm.metric_date, dam.metric_date, dqm.metric_date))::int AS month,
    EXTRACT(DAY   FROM COALESCE(dcm.metric_date, dtm.metric_date, dam.metric_date, dqm.metric_date))::int AS day,
    EXTRACT(DOW   FROM COALESCE(dcm.metric_date, dtm.metric_date, dam.metric_date, dqm.metric_date))::int AS day_of_week,

    CASE EXTRACT(DOW FROM COALESCE(dcm.metric_date, dtm.metric_date, dam.metric_date, dqm.metric_date))::int
        WHEN 0 THEN 'Sunday'
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
    END AS day_name,

    -- ==============================
    -- CALL KPIs
    -- ==============================
    COALESCE(dcm.total_calls, 0) AS total_calls,
    COALESCE(dcm.answered_calls, 0) AS answered_calls,
    COALESCE(dcm.abandoned_calls, 0) AS abandoned_calls,
    COALESCE(dcm.missed_calls, 0) AS missed_calls,

    CASE 
        WHEN COALESCE(dcm.answered_calls, 0) > 0
        THEN ROUND(dcm.sla_met_calls::numeric * 100.0 / dcm.answered_calls, 2)
        ELSE 0
    END AS sla_percentage,

    COALESCE(dcm.avg_wait_time_sec, 0) AS avg_wait_time_sec,

    COALESCE(dcm.top_queue, 'N/A') AS top_queue,

    -- ==============================
    -- TICKET KPIs
    -- ==============================
    COALESCE(dtm.top_issue_category, 'N/A') AS top_issue_category,
    COALESCE(dtm.total_tickets, 0) AS total_tickets,
    COALESCE(dtm.resolved_tickets, 0) AS resolved_tickets,
    COALESCE(dtm.critical_tickets, 0) AS critical_tickets,

    ROUND(COALESCE(dtm.avg_csat_score, 0), 2) AS avg_csat_score,

    -- ==============================
    -- AGENT KPIs
    -- ==============================
    COALESCE(dam.active_agents, 0) AS active_agents,
    ROUND(COALESCE(dam.avg_utilization_pct, 0), 2) AS avg_agent_utilization_pct,

    -- ==============================
    -- QUEUE KPIs
    -- ==============================
    COALESCE(dqm.max_queue_depth, 0) AS max_queue_depth,
    COALESCE(dqm.total_queue_abandons, 0) AS total_queue_abandons,
    ROUND(COALESCE(dqm.avg_service_level_pct, 0), 2) AS avg_service_level_pct

FROM daily_call_metrics dcm
FULL OUTER JOIN daily_ticket_metrics dtm
    ON dcm.metric_date = dtm.metric_date
FULL OUTER JOIN daily_agent_metrics dam
    ON COALESCE(dcm.metric_date, dtm.metric_date) = dam.metric_date
FULL OUTER JOIN daily_queue_metrics dqm
    ON COALESCE(dcm.metric_date, dtm.metric_date, dam.metric_date) = dqm.metric_date

WHERE COALESCE(dcm.metric_date, dtm.metric_date, dam.metric_date, dqm.metric_date)
      >= CURRENT_DATE - INTERVAL '90 days'

ORDER BY date DESC;
