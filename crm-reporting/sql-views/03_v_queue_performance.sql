-- ========================================
-- v_queue_performance
-- QUEUE / SKILL PERFORMANCE (DAILY QUEUE METRICS)
-- ========================================
-- Purpose: Queue service performance & kecekapan saluran
-- Use Cases:
--   - SLA % by queue
--   - Queue congestion trend
--   - Queue comparison dashboard
--   - Service level monitoring
-- Optimized for: Apache Superset, PowerBI, Tableau

CREATE OR REPLACE VIEW v_queue_performance AS
WITH mitel_queue_daily AS (
    -- Mitel queue performance aggregated daily
    SELECT
        mqp.queue_id,
        DATE(mqp.period_date) as metric_date,
        
        -- Call volume
        SUM(COALESCE(mqp.acd_count, 0)) as total_calls_answered,
        SUM(COALESCE(mqp.abandon_count, 0)) as total_calls_abandoned,
        SUM(COALESCE(mqp.acd_count, 0)) + SUM(COALESCE(mqp.abandon_count, 0)) as total_calls_offered,
        
        -- Time metrics (average calculations)
        CASE 
            WHEN SUM(COALESCE(mqp.acd_count, 0)) > 0 
            THEN ROUND(SUM(COALESCE(mqp.acd_duration, 0))::numeric / 
                       NULLIF(SUM(COALESCE(mqp.acd_count, 0)), 0), 2)
            ELSE 0 
        END as avg_talk_time_sec,
        
        -- Wait time not available in queue performance table, will use inbound trace data
        0 as avg_queue_wait_time_sec,
        
        MAX(COALESCE(mqp.max_waiting_calls, 0)) as max_queue_depth,
        
        -- Service level (need to calculate percentage)
        SUM(COALESCE(mqp.service_level, 0)) as sla_met_count,
        SUM(COALESCE(mqp.acd_count, 0)) as sla_total_calls
        
    FROM mitel_data_queue_performance mqp
    WHERE mqp.period_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY mqp.queue_id, DATE(mqp.period_date)
),

mitel_inbound_daily AS (
    -- Additional inbound trace metrics
    SELECT
        mit.queue_id,
        DATE(mit.call_date) as metric_date,
        
        -- Additional wait time metrics
        MAX(COALESCE(mit.time_to_answer, 0)) as max_wait_time_sec,
        MIN(CASE WHEN mit.time_to_answer > 0 THEN mit.time_to_answer ELSE NULL END) as min_wait_time_sec,
        AVG(COALESCE(mit.time_to_answer, 0)) as avg_wait_time_sec,
        
        -- Abandon metrics
        AVG(CASE WHEN mit.duration = 0 OR mit.time_to_answer IS NULL 
            THEN mit.time_to_answer ELSE NULL END) as avg_abandon_wait_sec
        
    FROM mitel_data_inbound_trace mit
    WHERE mit.call_date >= CURRENT_DATE - INTERVAL '90 days'
    GROUP BY mit.queue_id, DATE(mit.call_date)
),

crm_queue_calls AS (
    -- CRM customer interactions by queue
    SELECT
        ci.metadata ->> 'queue' as queue_name,
        DATE(ci.interaction_at) as metric_date,
        
        -- Call counts
        COUNT(*) as crm_total_calls,
        COUNT(CASE WHEN ci.metadata ->> 'callState' = 'answered' THEN 1 END) as crm_calls_answered,
        COUNT(CASE WHEN ci.metadata ->> 'callState' = 'abandoned' THEN 1 END) as crm_calls_abandoned,
        
        -- Time metrics
        AVG(CASE WHEN ci.metadata ->> 'callState' = 'answered' 
            THEN (ci.metadata ->> 'waitTime')::int END) as crm_avg_wait_time_sec,
        AVG(CASE WHEN ci.metadata ->> 'callState' = 'answered' 
            THEN (ci.metadata ->> 'callDuration')::int END) as crm_avg_call_duration_sec
        
    FROM customer_interactions ci
    WHERE ci.interaction_type = 'call'
      AND ci.metadata ->> 'queue' IS NOT NULL
      AND ci.interaction_at >= CURRENT_DATE - INTERVAL '90 days'
      AND ci.deleted_at IS NULL
    GROUP BY ci.metadata ->> 'queue', DATE(ci.interaction_at)
)

-- === MAIN QUERY ===
SELECT
    -- === QUEUE IDENTIFIERS ===
    mq.queue_id,
    mq.name as queue_name,
    mq.reporting_id as queue_reporting_id,
    
    -- === DATE DIMENSION ===
    COALESCE(mqd.metric_date, mid.metric_date, cqc.metric_date) as metric_date,
    EXTRACT(YEAR FROM COALESCE(mqd.metric_date, mid.metric_date, cqc.metric_date))::int as year,
    EXTRACT(MONTH FROM COALESCE(mqd.metric_date, mid.metric_date, cqc.metric_date))::int as month,
    EXTRACT(DAY FROM COALESCE(mqd.metric_date, mid.metric_date, cqc.metric_date))::int as day,
    EXTRACT(DOW FROM COALESCE(mqd.metric_date, mid.metric_date, cqc.metric_date))::int as day_of_week,
    CASE EXTRACT(DOW FROM COALESCE(mqd.metric_date, mid.metric_date, cqc.metric_date))::int
        WHEN 0 THEN 'Sunday'
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
    END as day_name,
    
    -- === CALL VOLUME METRICS ===
    COALESCE(mqd.total_calls_offered, 0) as total_calls_offered,
    COALESCE(mqd.total_calls_answered, 0) as total_calls_answered,
    COALESCE(mqd.total_calls_abandoned, 0) as total_calls_abandoned,
    
    -- CRM call volume (for comparison)
    COALESCE(cqc.crm_calls_answered, 0) as crm_calls_answered,
    COALESCE(cqc.crm_calls_abandoned, 0) as crm_calls_abandoned,
    
    -- === WAIT TIME METRICS (SECONDS) ===
    COALESCE(mid.avg_wait_time_sec, 0) as avg_queue_wait_time_sec,
    COALESCE(mid.max_wait_time_sec, 0) as max_queue_wait_time_sec,
    COALESCE(mid.min_wait_time_sec, 0) as min_queue_wait_time_sec,
    COALESCE(mid.avg_abandon_wait_sec, 0) as avg_abandon_wait_time_sec,
    
    -- === WAIT TIME METRICS (FORMATTED) ===
    TO_CHAR(INTERVAL '1 second' * COALESCE(mid.avg_wait_time_sec, 0), 'MI:SS') as avg_wait_formatted,
    TO_CHAR(INTERVAL '1 second' * COALESCE(mid.max_wait_time_sec, 0), 'MI:SS') as max_wait_formatted,
    
    -- === TALK TIME METRICS ===
    COALESCE(mqd.avg_talk_time_sec, 0) as avg_talk_time_sec,
    TO_CHAR(INTERVAL '1 second' * COALESCE(mqd.avg_talk_time_sec, 0), 'MI:SS') as avg_talk_time_formatted,
    
    -- === QUEUE DEPTH ===
    COALESCE(mqd.max_queue_depth, 0) as max_calls_waiting,
    
    -- === SLA THRESHOLD (FIXED AT 20 SECONDS FOR NOW) ===
    20 as sla_threshold_sec,
    
    -- === SLA COMPLIANCE ===
    -- SLA Met Flag: 1 if >= 80% of calls answered within threshold
    CASE 
        WHEN COALESCE(mqd.sla_total_calls, 0) > 0 
         AND (COALESCE(mqd.sla_met_count, 0)::numeric * 100.0 / mqd.sla_total_calls) >= 80 
        THEN 1 
        ELSE 0 
    END as sla_met_flag,
    
    -- SLA Percentage (calls answered within 20 seconds / total answered)
    CASE 
        WHEN COALESCE(mqd.sla_total_calls, 0) > 0 
        THEN ROUND(
            COALESCE(mqd.sla_met_count, 0)::numeric * 100.0 / 
            NULLIF(mqd.sla_total_calls, 0), 2)
        ELSE 0 
    END as sla_percentage,
    
    -- === SERVICE LEVEL RATING ===
    CASE 
        WHEN COALESCE(mqd.sla_total_calls, 0) = 0 THEN 'No Data'
        WHEN (COALESCE(mqd.sla_met_count, 0)::numeric * 100.0 / mqd.sla_total_calls) >= 80 THEN 'Excellent'
        WHEN (COALESCE(mqd.sla_met_count, 0)::numeric * 100.0 / mqd.sla_total_calls) >= 60 THEN 'Good'
        WHEN (COALESCE(mqd.sla_met_count, 0)::numeric * 100.0 / mqd.sla_total_calls) >= 40 THEN 'Fair'
        ELSE 'Poor'
    END as service_level_rating,
    
    -- === ABANDON RATE ===
    CASE 
        WHEN COALESCE(mqd.total_calls_offered, 0) > 0 
        THEN ROUND(
            COALESCE(mqd.total_calls_abandoned, 0)::numeric * 100.0 / 
            NULLIF(mqd.total_calls_offered, 0), 2)
        ELSE 0 
    END as abandon_rate_pct,
    
    -- === ANSWER RATE ===
    CASE 
        WHEN COALESCE(mqd.total_calls_offered, 0) > 0 
        THEN ROUND(
            COALESCE(mqd.total_calls_answered, 0)::numeric * 100.0 / 
            NULLIF(mqd.total_calls_offered, 0), 2)
        ELSE 0 
    END as answer_rate_pct,
    
    -- === QUEUE EFFICIENCY ===
    -- Short abandons (< 10 seconds) indicate IVR or routing issues
    CASE 
        WHEN COALESCE(mid.avg_abandon_wait_sec, 0) < 10 THEN 'High Short Abandons - Check IVR'
        WHEN COALESCE(mid.avg_abandon_wait_sec, 0) > 60 THEN 'High Long Abandons - Understaffing'
        WHEN COALESCE(mqd.max_queue_depth, 0) > 20 THEN 'High Queue Depth - Capacity Issue'
        ELSE 'Normal'
    END as efficiency_flag

FROM mitel_config_queues mq
LEFT JOIN mitel_queue_daily mqd ON mq.queue_id = mqd.queue_id
LEFT JOIN mitel_inbound_daily mid ON mq.queue_id = mid.queue_id 
    AND mqd.metric_date = mid.metric_date
LEFT JOIN crm_queue_calls cqc ON mq.name = cqc.queue_name 
    AND COALESCE(mqd.metric_date, mid.metric_date) = cqc.metric_date

WHERE (mqd.metric_date IS NOT NULL OR mid.metric_date IS NOT NULL OR cqc.metric_date IS NOT NULL)
;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. SLA % by queue (last 30 days)
SELECT 
    queue_name,
    AVG(sla_percentage) as avg_sla_pct,
    SUM(total_calls_offered) as total_calls,
    SUM(total_calls_answered) as answered,
    SUM(total_calls_abandoned) as abandoned
FROM v_queue_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY queue_name
ORDER BY avg_sla_pct DESC;

-- 2. Queue congestion trend
SELECT 
    metric_date,
    queue_name,
    max_calls_waiting,
    avg_queue_wait_time_sec,
    total_calls_offered
FROM v_queue_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '30 days'
ORDER BY metric_date DESC, max_calls_waiting DESC;

-- 3. Queue comparison dashboard
SELECT 
    queue_name,
    AVG(sla_percentage) as avg_sla,
    AVG(abandon_rate_pct) as avg_abandon_rate,
    AVG(avg_queue_wait_time_sec) as avg_wait_sec,
    SUM(total_calls_offered) as total_volume
FROM v_queue_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY queue_name
ORDER BY total_volume DESC;

-- 4. Service level distribution
SELECT 
    service_level_rating,
    COUNT(*) as day_count,
    AVG(sla_percentage) as avg_sla,
    AVG(total_calls_offered) as avg_volume
FROM v_queue_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY service_level_rating
ORDER BY 
    CASE service_level_rating
        WHEN 'Excellent' THEN 1
        WHEN 'Good' THEN 2
        WHEN 'Fair' THEN 3
        WHEN 'Poor' THEN 4
        ELSE 5
    END;

-- 5. Queue efficiency flags
SELECT 
    metric_date,
    queue_name,
    efficiency_flag,
    avg_abandon_wait_time_sec,
    max_calls_waiting,
    abandon_rate_pct
FROM v_queue_performance
WHERE metric_date >= CURRENT_DATE - INTERVAL '7 days'
  AND efficiency_flag != 'Normal'
ORDER BY metric_date DESC, queue_name;

*/
