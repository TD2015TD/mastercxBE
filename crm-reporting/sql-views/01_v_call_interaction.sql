-- ========================================
-- v_call_interaction
-- CORE – Call / Interaction Level (FOUNDATION VIEW)
-- ========================================
-- Purpose: Flattened, BI-friendly view of all call/interaction details
-- Data Source: Mitel Inbound/Outbound Trace (authoritative source)
-- Use Cases:
--   - Total calls (daily/hourly/monthly)
--   - Inbound vs outbound trend
--   - Peak hour heatmap
--   - Answered vs abandoned ratio
--   - Service level analysis
-- Optimized for: Apache Superset, PowerBI, Tableau

DROP VIEW IF EXISTS v_call_interaction CASCADE;

CREATE VIEW v_call_interaction AS
WITH inbound_calls AS (
    SELECT
        maicd.extension_id::text as id,
        maicd.extension_id::text as call_id,  -- Using extension_id as unique identifier
        maicd.queue_id::text,
        maicd.agent_id::text as mitel_agent_id,
        maicd.midnight_start_datetime as call_start_time,
        maicd.midnight_start_datetime + INTERVAL '1 second' * COALESCE(maicd.time_to_answer, 0) as call_answer_time,
        maicd.midnight_start_datetime + INTERVAL '1 second' * COALESCE(maicd.duration, 0) as call_end_time,
        -- Direction: Internal (InternalNonACD/InternalACD) or Inbound (external)
        CASE 
            WHEN maicd.call_type LIKE 'Internal%' THEN 'Internal'
            ELSE 'Inbound'
        END as direction,
        'call' as channel_type,
        
        -- Phone numbers
        -- Caller number: From ANI (Automatic Number Identification)
        COALESCE(
            NULLIF(maicd.ani_name, ''),
            CASE WHEN maicd.ani_id::text != '00000000-0000-0000-0000-000000000000' 
                 THEN maicd.ani_reporting
                 ELSE ''
            END
        ) as caller_number,
        
        -- Called number: From digits_dialed (the number/extension that was dialed)
        -- For internal calls: extension like "9202", "4202"
        -- For ACD calls: queue/agent group combo like "P433 038 038"
        COALESCE(
            NULLIF(maicd.digits_dialed, ''),
            NULLIF(maicd.dnis_name, ''),
            CASE WHEN maicd.dnis_id::text != '00000000-0000-0000-0000-000000000000' 
                 THEN maicd.dnis_reporting
                 ELSE ''
            END
        ) as called_number,
        
        -- Call state (derived from call_type)
        CASE 
            WHEN maicd.call_type LIKE '%Answered%' THEN 'answered'
            WHEN maicd.call_type LIKE '%Abandoned%' THEN 'abandoned'
            WHEN maicd.call_type LIKE '%Missed%' THEN 'missed'
            WHEN maicd.duration > 0 AND maicd.time_to_answer > 0 THEN 'answered'
            WHEN maicd.duration = 0 OR maicd.time_to_answer IS NULL THEN 'abandoned'
            ELSE 'other'
        END as call_state,
        
        -- Time metrics
        COALESCE(maicd.time_to_answer, 0) as ring_time_sec,
        COALESCE(maicd.time_to_answer, 0) as queue_wait_time_sec,
        COALESCE(maicd.duration, 0) as talk_time_sec,
        0 as hold_time_sec,
        0 as wrap_up_time_sec,
        CASE WHEN maicd.duration = 0 THEN COALESCE(maicd.time_to_answer, 0) ELSE 0 END as abandon_time_sec,
        
        -- Disconnect reason (not available in Mitel data)
        NULL::text as disconnect_reason,
        
        -- Timestamps
        maicd.synced_at as record_created_at,
        maicd.updated_at as record_updated_at
        
    FROM mitel_agent_inbound_call_details maicd
),

outbound_calls AS (
    SELECT
        maocd.extension_id::text as id,
        maocd.extension_id::text as call_id,  -- Using extension_id as unique identifier
        NULL::text as queue_id,  -- Not available in outbound calls
        maocd.agent_id::text as mitel_agent_id,
        maocd.midnight_start_datetime as call_start_time,
        maocd.midnight_start_datetime as call_answer_time,  -- Assume answered immediately for outbound
        maocd.midnight_start_datetime + INTERVAL '1 second' * COALESCE(maocd.duration, 0) as call_end_time,
        'Outbound' as direction,
        'call' as channel_type,
        
        -- Phone numbers
        -- Caller number: Agent's extension (from extension_reporting)
        COALESCE(maocd.extension_reporting, '') as caller_number,
        
        -- Called number: From phone_number field (the number dialed)
        COALESCE(maocd.phone_number, '') as called_number,
        
        -- Call state (derived from call_type)
        CASE 
            WHEN maocd.call_type LIKE '%Answered%' THEN 'answered'
            WHEN maocd.call_type LIKE '%Abandoned%' THEN 'abandoned'
            WHEN maocd.call_type LIKE '%Missed%' THEN 'missed'
            WHEN maocd.duration > 0 THEN 'answered'
            ELSE 'abandoned'
        END as call_state,
        
        -- Time metrics (outbound has no queue wait time)
        0 as ring_time_sec,
        0 as queue_wait_time_sec,
        COALESCE(maocd.duration, 0) as talk_time_sec,
        0 as hold_time_sec,
        0 as wrap_up_time_sec,
        0 as abandon_time_sec,
        
        -- Disconnect reason (not available in Mitel data)
        NULL::text as disconnect_reason,
        
        -- Timestamps
        maocd.synced_at as record_created_at,
        maocd.updated_at as record_updated_at
        
    FROM mitel_agent_outbound_call_details maocd
),

all_calls AS (
    SELECT * FROM inbound_calls
    UNION ALL
    SELECT * FROM outbound_calls
),

linked_tickets AS (
    -- Link Mitel calls to CRM tickets via:
    -- 1. Direct call_id match (primary, when call_id is not empty)
    -- 2. Phone number + time range (fallback within ±10 minutes)
    SELECT DISTINCT ON (ac.id)
        ac.id,
        ac.call_id,
        ac.call_start_time,
        t.id as ticket_id,
        t.ticket_number,
        t.subject as ticket_subject,
        t.priority as ticket_priority,
        t.category as ticket_category,
        t.status as ticket_status,
        t.customer_id as ticket_customer_id,
        CASE 
            WHEN ac.call_id != '' AND ac.call_id = t.call_id THEN 'call_id'
            ELSE 'phone_time'
        END as link_method
    FROM all_calls ac
    LEFT JOIN tickets t ON (
        -- Primary: Direct call_id match (only when call_id is not empty)
        (ac.call_id != '' AND ac.call_id = t.call_id)
        OR
        -- Fallback: Time range match
        (
            t.created_at BETWEEN ac.call_start_time - INTERVAL '10 minutes'
                             AND ac.call_start_time + INTERVAL '10 minutes'
            AND ac.caller_number IS NOT NULL
            AND ac.caller_number != ''
        )
    )
    WHERE t.deleted_at IS NULL OR t.id IS NULL
    ORDER BY ac.id,
             CASE WHEN ac.call_id != '' AND ac.call_id = t.call_id THEN 1 ELSE 2 END,
             t.created_at ASC
)

-- === MAIN QUERY ===
SELECT
    -- === PRIMARY IDENTIFIERS ===
    COALESCE(NULLIF(ac.call_id, ''), 'MIT-' || ac.id::text) as interaction_id,
    ac.call_id,
    
    -- === TIMESTAMPS (CRITICAL FOR TIME-BASED ANALYSIS) ===
    ac.call_start_time,
    ac.call_answer_time,
    ac.call_end_time,
    DATE(ac.call_start_time) as call_date,
    EXTRACT(YEAR FROM ac.call_start_time)::int as call_year,
    EXTRACT(MONTH FROM ac.call_start_time)::int as call_month,
    EXTRACT(DAY FROM ac.call_start_time)::int as call_day,
    EXTRACT(HOUR FROM ac.call_start_time)::int as call_hour,
    EXTRACT(DOW FROM ac.call_start_time)::int as day_of_week,  -- 0=Sunday, 6=Saturday
    CASE EXTRACT(DOW FROM ac.call_start_time)::int
        WHEN 0 THEN 'Sunday'
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
    END as day_name,
    
    -- === CALL DIRECTION & TYPE ===
    ac.direction,
    ac.channel_type,
    
    -- === PHONE NUMBERS ===
    COALESCE(ac.caller_number, '') as caller_number,  -- CLI
    COALESCE(ac.called_number, '') as called_number,  -- DNIS
    
    -- === QUEUE / SKILL ===
    ac.queue_id::text as queue_name,
    ac.queue_id as queue_reporting_id,
    NULL as skill,  -- Not available in Mitel trace
    
    -- === AGENT INFORMATION (FROM CRM SYSTEM) ===
    a.id as agent_id,
    ac.mitel_agent_id,
    u.name as agent_name,
    a.agent_code,
    a.employee_id,
    a.mitel_extension as agent_extension,
    a.department as agent_department,
    a.designation as agent_designation,
    
    -- === CALL STATUS (CRITICAL FOR KPI) ===
    CASE 
        WHEN ac.call_state IN ('answered', 'connected', 'completed') THEN 'Answered'
        WHEN ac.call_state IN ('abandoned', 'caller_abandoned') THEN 'Abandoned'
        WHEN ac.call_state IN ('missed', 'no_answer') THEN 'Missed'
        WHEN ac.call_state = 'voicemail' THEN 'Voicemail'
        WHEN ac.call_state IN ('transferred', 'forwarded') THEN 'Transferred'
        ELSE COALESCE(UPPER(SUBSTRING(ac.call_state, 1, 1)) || 
                      LOWER(SUBSTRING(ac.call_state, 2)), 'Unknown')
    END as call_status,
    
    -- === STATUS FLAGS (FOR QUICK FILTERING) ===
    CASE WHEN ac.call_state IN ('answered', 'connected', 'completed') THEN 1 ELSE 0 END as is_answered,
    CASE WHEN ac.call_state IN ('abandoned', 'caller_abandoned') THEN 1 ELSE 0 END as is_abandoned,
    CASE WHEN ac.call_state = 'voicemail' THEN 1 ELSE 0 END as is_voicemail,
    CASE WHEN ac.call_state IN ('transferred', 'forwarded') THEN 1 ELSE 0 END as is_transferred,
    CASE WHEN ac.call_state IN ('missed', 'no_answer') THEN 1 ELSE 0 END as is_missed,
    
    -- === DISCONNECT REASON ===
    COALESCE(ac.disconnect_reason, 'Normal') as disconnect_reason,
    
    -- === TIME METRICS (SECONDS) - FOR PERFORMANCE KPI ===
    ac.ring_time_sec,
    ac.queue_wait_time_sec,
    ac.talk_time_sec,
    ac.hold_time_sec,
    ac.wrap_up_time_sec,
    
    -- === TOTAL HANDLE TIME (AHT) ===
    ac.talk_time_sec + ac.wrap_up_time_sec as total_handle_time_sec,
    
    -- === ABANDON TIME (FOR ABANDONED CALLS) ===
    ac.abandon_time_sec,
    
    -- === SHORT ABANDON FLAG (< 10 seconds) ===
    CASE 
        WHEN ac.call_state IN ('abandoned', 'caller_abandoned') 
         AND ac.abandon_time_sec < 10 
        THEN 1 
        ELSE 0 
    END as is_short_abandon,
    
    -- === SLA METRICS ===
    CASE 
        WHEN ac.call_state IN ('answered', 'connected', 'completed') 
         AND ac.queue_wait_time_sec <= 20 
        THEN 1 
        ELSE 0 
    END as sla_20sec_met,
    
    CASE 
        WHEN ac.call_state IN ('answered', 'connected', 'completed') 
         AND ac.queue_wait_time_sec <= 30 
        THEN 1 
        ELSE 0 
    END as sla_30sec_met,
    
    -- === CUSTOMER INFORMATION ===
    -- Linked via ticket's customer_id (phone_number removed from customers table)
    c.id as customer_id,
    c.name as customer_name,
    c.email as customer_email,
    NULL::text as customer_phone,
    
    -- === TICKET LINKING (VIA CALL_ID OR PHONE+TIME) ===
    lt.ticket_id,
    lt.ticket_number,
    lt.ticket_subject,
    lt.ticket_priority,
    lt.ticket_category,
    lt.ticket_status,
    lt.ticket_customer_id,
    lt.link_method as ticket_link_method,
    
    -- === METADATA & AUDIT ===
    ac.record_created_at,
    ac.record_updated_at,
    NULL as record_deleted_at,
    1 as is_active

FROM all_calls ac
-- Join Mitel config agents using agent_id from trace (matches FKMasterAgent in config)
-- Case-insensitive comparison since call records are lowercase, config is uppercase
LEFT JOIN mitel_config_agents mca ON LOWER(ac.mitel_agent_id::text) = LOWER(mca.fk_master_agent)
-- Join CRM agents via employee_id UUID match (case-insensitive: Mitel stores uppercase, CRM lowercase)
LEFT JOIN agents a ON LOWER(mca.fk_employee) = a.employee_id
LEFT JOIN users u ON a.user_id = u.id
-- Join tickets via CTE (supports call_id and phone+time matching)
LEFT JOIN linked_tickets lt ON ac.id = lt.id
-- Join customers via ticket's customer_id (phone_number no longer in customers table)
LEFT JOIN customers c ON c.id = lt.ticket_customer_id AND c.deleted_at IS NULL
;

-- === INDEXES FOR PERFORMANCE ===
-- Note: Views cannot have indexes, but you can create indexes on the underlying table
-- Recommended indexes on customer_interactions table:
-- CREATE INDEX idx_ci_interaction_at ON customer_interactions(interaction_at);
-- CREATE INDEX idx_ci_agent_id ON customer_interactions(agent_id);
-- CREATE INDEX idx_ci_call_date ON customer_interactions(DATE(interaction_at));
-- CREATE INDEX idx_ci_call_status ON customer_interactions((metadata ->> 'callState'));

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Total calls by date
SELECT call_date, COUNT(*) as total_calls
FROM v_call_interaction
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY call_date
ORDER BY call_date;

-- 2. Inbound vs Outbound trend
SELECT call_date, direction, COUNT(*) as call_count
FROM v_call_interaction
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY call_date, direction
ORDER BY call_date, direction;

-- 3. Peak hour heatmap
SELECT call_hour, day_name, COUNT(*) as call_volume
FROM v_call_interaction
WHERE call_date >= CURRENT_DATE - INTERVAL '90 days'
GROUP BY call_hour, day_name
ORDER BY call_hour, 
         CASE day_name 
            WHEN 'Monday' THEN 1 
            WHEN 'Tuesday' THEN 2 
            WHEN 'Wednesday' THEN 3 
            WHEN 'Thursday' THEN 4 
            WHEN 'Friday' THEN 5 
            WHEN 'Saturday' THEN 6 
            WHEN 'Sunday' THEN 7 
         END;

-- 4. Answered vs Abandoned ratio
SELECT 
    call_date,
    SUM(is_answered) as answered,
    SUM(is_abandoned) as abandoned,
    ROUND(SUM(is_abandoned)::numeric * 100.0 / NULLIF(COUNT(*), 0), 2) as abandon_rate
FROM v_call_interaction
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY call_date
ORDER BY call_date;

-- 5. Queue performance
SELECT 
    queue_name,
    COUNT(*) as total_calls,
    SUM(is_answered) as answered,
    SUM(is_abandoned) as abandoned,
    ROUND(AVG(queue_wait_time_sec), 2) as avg_wait_sec,
    ROUND(SUM(sla_20sec_met)::numeric * 100.0 / NULLIF(SUM(is_answered), 0), 2) as sla_20sec_pct
FROM v_call_interaction
WHERE call_date >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY queue_name
ORDER BY total_calls DESC;

*/