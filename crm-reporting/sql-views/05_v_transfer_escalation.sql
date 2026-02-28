-- ========================================
-- v_transfer_escalation
-- TRANSFER & ESCALATION DATA
-- ========================================
-- Purpose: FCR proxy metrics - identify transfer patterns & escalation hotspots
-- Data Source: Mitel inbound/outbound traces + CRM tickets
-- Use Cases:
--   - FCR proxy calculation
--   - Escalation hotspot identification
--   - Training gap indicator
--   - Transfer pattern analysis
-- Optimized for: Apache Superset, PowerBI, Tableau

CREATE OR REPLACE VIEW v_transfer_escalation AS
WITH mitel_call_transfers AS (
    -- Extract transfer information from Mitel inbound trace
    SELECT
        mit.call_id,
        mit.agent_id as mitel_agent_id,
        mit.queue_id,
        mit.call_date as call_start_time,
        DATE(mit.call_date) as call_date,
        
        -- Transfer metrics (Note: basic Mitel table may not have transfer fields)
        0 as transfer_count,
        NULL as transfer_from,
        NULL as transfer_to,
        NULL as transfer_type,
        
        -- Call metrics
        COALESCE(mit.duration, 0) as call_duration,
        CASE 
            WHEN mit.duration > 0 AND mit.time_to_answer > 0 THEN 'answered'
            WHEN mit.duration = 0 OR mit.time_to_answer IS NULL THEN 'abandoned'
            ELSE 'other'
        END as call_state
        
    FROM mitel_data_inbound_trace mit
    WHERE mit.call_date >= CURRENT_DATE - INTERVAL '90 days'
    
    UNION ALL
    
    -- Outbound calls (rarely have transfers but include for completeness)
    SELECT
        mot.call_id,
        mot.agent_id as mitel_agent_id,
        mot.queue_id,
        mot.call_date as call_start_time,
        DATE(mot.call_date) as call_date,
        
        0 as transfer_count,
        NULL as transfer_from,
        NULL as transfer_to,
        NULL as transfer_type,
        
        COALESCE(mot.duration, 0) as call_duration,
        CASE 
            WHEN mot.duration > 0 AND mot.time_to_answer > 0 THEN 'answered'
            WHEN mot.duration = 0 OR mot.time_to_answer IS NULL THEN 'abandoned'
            ELSE 'other'
        END as call_state
        
    FROM mitel_data_outbound_trace mot
    WHERE mot.call_date >= CURRENT_DATE - INTERVAL '90 days'
),

ticket_escalations AS (
    -- Identify ticket escalations from ticket status changes
    -- Link to Mitel calls via call_id or phone+time matching
    SELECT DISTINCT ON (mct.call_id, mct.call_start_time)
        mct.call_id,
        mct.call_start_time,
        t.id as ticket_id,
        t.ticket_number,
        t.customer_id,
        t.assigned_to as agent_id,
        t.created_at,
        DATE(t.created_at) as ticket_date,
        
        -- Escalation indicators
        CASE 
            WHEN t.status = 'Escalated' THEN 1
            WHEN t.priority = 'Critical' AND t.status NOT IN ('Resolved', 'Closed') THEN 1
            ELSE 0
        END as is_escalated,
        
        -- Priority changes indicate escalation
        CASE 
            WHEN t.priority IN ('High', 'Critical') THEN 1
            ELSE 0
        END as is_high_priority,
        
        t.status as ticket_status,
        t.priority as ticket_priority,
        t.category as ticket_category,
        
        CASE 
            WHEN mct.call_id != '' AND mct.call_id = t.call_id THEN 'call_id'
            ELSE 'phone_time'
        END as link_method
        
    FROM mitel_call_transfers mct
    LEFT JOIN tickets t ON (
        -- Primary: Direct call_id match (only when call_id is not empty)
        (mct.call_id != '' AND mct.call_id = t.call_id)
        OR
        -- Fallback: Phone number (digits_dialed) + time range match
        (
            t.created_at BETWEEN mct.call_start_time - INTERVAL '10 minutes' 
                             AND mct.call_start_time + INTERVAL '10 minutes'
            AND (\
                -- Match via customer phone in customers table (need to extract phone from call)
                EXISTS (
                    SELECT 1 FROM mitel_data_inbound_trace mit
                    JOIN customers c ON c.id = t.customer_id
                    WHERE mit.call_id = mct.call_id
                    AND c.phone_number = REGEXP_REPLACE(mit.digits_dialed, '[^0-9]', '', 'g')
                )
                OR
                EXISTS (
                    SELECT 1 FROM mitel_data_outbound_trace mot
                    JOIN customers c ON c.id = t.customer_id
                    WHERE mot.call_id = mct.call_id
                    AND c.phone_number = REGEXP_REPLACE(mot.digits_dialed, '[^0-9]', '', 'g')
                )
            )
        )
    )
    WHERE (t.created_at >= CURRENT_DATE - INTERVAL '90 days' OR t.id IS NULL)
      AND (t.deleted_at IS NULL OR t.id IS NULL)
    ORDER BY mct.call_id, mct.call_start_time,
             CASE WHEN mct.call_id != '' AND mct.call_id = t.call_id THEN 1 ELSE 2 END,
             t.created_at ASC
),

agent_transfer_patterns AS (
    -- Aggregate transfer patterns by agent
    SELECT
        mitel_agent_id,
        COUNT(*) as total_calls,
        SUM(transfer_count) as total_transfers,
        COUNT(CASE WHEN transfer_count > 0 THEN 1 END) as calls_with_transfers,
        AVG(CASE WHEN transfer_count > 0 THEN transfer_count END) as avg_transfers_per_call,
        
        -- Transfer types
        COUNT(CASE WHEN transfer_type = 'blind' THEN 1 END) as blind_transfers,
        COUNT(CASE WHEN transfer_type = 'consult' THEN 1 END) as consult_transfers
        
    FROM mitel_call_transfers
    WHERE mitel_agent_id IS NOT NULL
    GROUP BY mitel_agent_id
)

-- === MAIN QUERY ===
SELECT
    -- === IDENTIFIERS ===
    mct.call_id as interaction_id,
    mct.call_id,
    te.ticket_id,
    te.ticket_number,
    
    -- === AGENT INFORMATION ===
    mct.mitel_agent_id,
    a.id as agent_id,
    u.name as agent_name,
    a.employee_id as agent_extension,
    a.department,
    
    -- === TIMESTAMPS ===
    mct.call_start_time as call_time,
    mct.call_date,
    EXTRACT(HOUR FROM mct.call_start_time)::int as call_hour,
    EXTRACT(DOW FROM mct.call_date)::int as day_of_week,
    
    -- === TRANSFER METRICS ===
    mct.transfer_count,
    CASE 
        WHEN mct.transfer_count = 0 THEN 'No Transfer'
        WHEN mct.transfer_count = 1 THEN 'Single Transfer'
        WHEN mct.transfer_count = 2 THEN 'Double Transfer'
        WHEN mct.transfer_count >= 3 THEN 'Multiple Transfers (3+)'
    END as transfer_category,
    
    mct.transfer_from,
    mct.transfer_to,
    mct.transfer_type,
    
    -- Transfer type classification
    CASE 
        WHEN mct.transfer_type = 'blind' THEN 'Blind Transfer'
        WHEN mct.transfer_type = 'consult' THEN 'Consult Transfer'
        WHEN mct.transfer_type = 'warm' THEN 'Warm Transfer'
        ELSE 'Unknown'
    END as transfer_type_label,
    
    -- === ESCALATION FLAGS ===
    COALESCE(te.is_escalated, 0) as escalation_flag,
    COALESCE(te.is_high_priority, 0) as high_priority_flag,
    
    CASE 
        WHEN te.is_escalated = 1 THEN 'Escalated'
        WHEN te.is_high_priority = 1 THEN 'High Priority'
        WHEN mct.transfer_count >= 2 THEN 'Multiple Transfers'
        WHEN mct.transfer_count = 1 THEN 'Single Transfer'
        ELSE 'Direct Resolution'
    END as handling_complexity,
    
    -- === QUEUE INFORMATION ===
    mq.name as queue_name,
    
    -- === TICKET INFORMATION ===
    te.ticket_status,
    te.ticket_priority,
    te.ticket_category,
    
    -- === CALL METRICS ===
    mct.call_duration as call_duration_sec,
    CASE 
        WHEN mct.call_state IN ('answered', 'completed') THEN 'Answered'
        WHEN mct.call_state IN ('abandoned', 'caller_abandoned') THEN 'Abandoned'
        WHEN mct.call_state = 'transferred' THEN 'Transferred'
        ELSE 'Other'
    END as call_status,
    TO_CHAR(INTERVAL '1 second' * mct.call_duration, 'MI:SS') as call_duration_formatted,
    
    -- === FCR PROXY CALCULATION ===
    -- FCR is impacted by transfers & escalations
    CASE 
        WHEN mct.transfer_count = 0 
         AND COALESCE(te.is_escalated, 0) = 0 
         AND te.ticket_status IN ('Resolved', 'Closed')
        THEN 1 
        ELSE 0 
    END as fcr_proxy,
    
    CASE 
        WHEN mct.transfer_count = 0 AND COALESCE(te.is_escalated, 0) = 0 THEN 'Good FCR'
        WHEN mct.transfer_count = 1 AND COALESCE(te.is_escalated, 0) = 0 THEN 'Fair - One Transfer'
        WHEN mct.transfer_count >= 2 THEN 'Poor - Multiple Transfers'
        WHEN COALESCE(te.is_escalated, 0) = 1 THEN 'Poor - Escalated'
        ELSE 'Unknown'
    END as fcr_impact,
    
    -- === TRAINING GAP INDICATORS ===
    -- High transfers from specific agent/queue = training need
    CASE 
        WHEN mct.transfer_count >= 2 THEN 'High Transfer Rate - Training Needed'
        WHEN COALESCE(te.is_escalated, 0) = 1 AND mct.transfer_count > 0 THEN 'Transfer + Escalation - Skill Gap'
        WHEN mct.transfer_type = 'blind' AND mct.transfer_count > 1 THEN 'Multiple Blind Transfers - Knowledge Gap'
        ELSE 'Normal'
    END as training_indicator,
    
    -- === AGENT TRANSFER PATTERN ===
    atp.total_calls as agent_total_calls,
    atp.calls_with_transfers as agent_calls_with_transfers,
    ROUND(COALESCE(atp.calls_with_transfers::numeric * 100.0 / NULLIF(atp.total_calls, 0), 0), 2) as agent_transfer_rate_pct,
    
    -- === ESCALATION HOTSPOT ===
    -- Combination of queue + category for identifying problem areas
    COALESCE(mq.name, 'Unknown') || ' - ' || COALESCE(te.ticket_category, 'Unknown') as escalation_hotspot,
    
    -- === CUSTOMER INFORMATION ===
    te.customer_id,
    c.name as customer_name,
    c.email as customer_email,
    
    -- === DATA QUALITY ===
    CASE 
        WHEN mct.transfer_count > 0 AND mct.transfer_to IS NULL THEN 'Missing Transfer Destination'
        WHEN mct.transfer_count > 0 AND mct.transfer_type IS NULL THEN 'Missing Transfer Type'
        WHEN te.ticket_id IS NULL THEN 'No Ticket Link'
        ELSE 'Complete'
    END as data_quality,
    
    -- === AUDIT FIELDS ===
    CASE WHEN mct.call_id IS NOT NULL THEN 1 ELSE 0 END as is_active

FROM mitel_call_transfers mct
LEFT JOIN mitel_config_queues mq ON mct.queue_id::text = mq.queue_id::text
LEFT JOIN mitel_config_agents mca ON mct.mitel_agent_id::text = mca.fk_master_agent
LEFT JOIN agents a ON mca.fk_employee = UPPER(a.employee_id)
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN ticket_escalations te ON mct.call_id = te.call_id AND mct.call_start_time = te.call_start_time
LEFT JOIN customers c ON te.customer_id = c.id
LEFT JOIN agent_transfer_patterns atp ON mct.mitel_agent_id = atp.mitel_agent_id

WHERE mct.call_date >= CURRENT_DATE - INTERVAL '90 days'
;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. FCR proxy metrics (last 30 days)
SELECT 
    call_date,
    COUNT(*) as total_calls,
    SUM(fcr_proxy) as fcr_calls,
    ROUND(SUM(fcr_proxy)::numeric * 100.0 / COUNT(*), 2) as fcr_rate
FROM v_transfer_escalation
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY call_date
ORDER BY call_date DESC;

-- 2. Escalation hotspots (top 10)
SELECT 
    escalation_hotspot,
    COUNT(*) as incident_count,
    SUM(escalation_flag) as escalated_count,
    AVG(transfer_count) as avg_transfers,
    ROUND(SUM(escalation_flag)::numeric * 100.0 / COUNT(*), 2) as escalation_rate
FROM v_transfer_escalation
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY escalation_hotspot
ORDER BY escalated_count DESC
LIMIT 10;

-- 3. Training gap indicators by agent
SELECT 
    agent_name,
    department,
    COUNT(*) as total_calls,
    AVG(transfer_count) as avg_transfers,
    COUNT(CASE WHEN training_indicator != 'Normal' THEN 1 END) as training_needed_calls,
    ROUND(COUNT(CASE WHEN training_indicator != 'Normal' THEN 1 END)::numeric * 100.0 / COUNT(*), 2) as training_gap_pct
FROM v_transfer_escalation
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
  AND agent_id IS NOT NULL
GROUP BY agent_name, department
HAVING COUNT(CASE WHEN training_indicator != 'Normal' THEN 1 END) > 0
ORDER BY training_gap_pct DESC
LIMIT 20;

-- 4. Transfer pattern analysis
SELECT 
    transfer_category,
    transfer_type_label,
    COUNT(*) as call_count,
    AVG(call_duration_sec) as avg_duration_sec,
    SUM(fcr_proxy) as fcr_count
FROM v_transfer_escalation
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY transfer_category, transfer_type_label
ORDER BY call_count DESC;

-- 5. Queue transfer performance
SELECT 
    queue_name,
    COUNT(*) as total_calls,
    AVG(transfer_count) as avg_transfers,
    COUNT(CASE WHEN transfer_count = 0 THEN 1 END) as no_transfer_calls,
    ROUND(COUNT(CASE WHEN transfer_count = 0 THEN 1 END)::numeric * 100.0 / COUNT(*), 2) as direct_resolution_rate
FROM v_transfer_escalation
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY queue_name
ORDER BY avg_transfers DESC;

-- 6. Handling complexity distribution
SELECT 
    handling_complexity,
    COUNT(*) as call_count,
    AVG(call_duration_sec) as avg_duration,
    SUM(fcr_proxy) as fcr_count,
    ROUND(SUM(fcr_proxy)::numeric * 100.0 / COUNT(*), 2) as fcr_rate
FROM v_transfer_escalation
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY handling_complexity
ORDER BY 
    CASE handling_complexity
        WHEN 'Direct Resolution' THEN 1
        WHEN 'Single Transfer' THEN 2
        WHEN 'Multiple Transfers' THEN 3
        WHEN 'High Priority' THEN 4
        WHEN 'Escalated' THEN 5
    END;

-- 7. Agent transfer rate ranking
SELECT 
    agent_name,
    department,
    agent_total_calls,
    agent_calls_with_transfers,
    agent_transfer_rate_pct
FROM v_transfer_escalation
WHERE agent_id IS NOT NULL
  AND agent_total_calls >= 10  -- Minimum volume threshold
GROUP BY agent_name, department, agent_total_calls, agent_calls_with_transfers, agent_transfer_rate_pct
ORDER BY agent_transfer_rate_pct DESC
LIMIT 20;

*/
