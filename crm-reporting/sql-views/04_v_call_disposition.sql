-- ========================================
-- v_call_disposition
-- WRAP-UP / DISPOSITION DATA
-- ========================================
-- Purpose: Telephony + CRM complete - wrap-up codes & ticket resolution
-- Data Source: Mitel traces + CRM tickets (linked via agent/time)
-- Use Cases:
--   - Call reason distribution
--   - Issue trend analysis
--   - Top 10 complaint type
--   - Ops insight untuk policy / SOP
-- Optimized for: Apache Superset, PowerBI, Tableau

DROP VIEW IF EXISTS v_call_disposition;

CREATE VIEW v_call_disposition AS
WITH mitel_calls AS (
    -- Inbound calls
    SELECT
        mit.call_id,
        mit.agent_id as mitel_agent_id,
        mit.queue_id,
        mit.call_date as call_start_time,
        'Inbound' as direction,
        CASE 
            WHEN mit.duration > 0 AND mit.time_to_answer > 0 THEN 'answered'
            WHEN mit.duration = 0 OR mit.time_to_answer IS NULL THEN 'abandoned'
            ELSE 'other'
        END as call_state,
        COALESCE(mit.duration, 0) as talk_time_sec,
        COALESCE(mit.time_to_answer, 0) as wait_time_sec,
        0 as wrap_up_time_sec,
        mit.digits_dialed as disconnect_reason
    FROM mitel_data_inbound_trace mit
    WHERE mit.call_date >= CURRENT_DATE - INTERVAL '90 days'
    
    UNION ALL
    
    -- Outbound calls
    SELECT
        mot.call_id,
        mot.agent_id as mitel_agent_id,
        NULL::text as queue_id,  -- Outbound calls don't have queue_id
        mot.call_date as call_start_time,
        'Outbound' as direction,
        CASE 
            WHEN mot.duration > 0 THEN 'answered'
            ELSE 'abandoned'
        END as call_state,
        COALESCE(mot.duration, 0) as talk_time_sec,
        0 as wait_time_sec,
        0 as wrap_up_time_sec,
        mot.digits_dialed as disconnect_reason
    FROM mitel_data_outbound_trace mot
    WHERE mot.call_date >= CURRENT_DATE - INTERVAL '90 days'
),

linked_tickets AS (
    -- Link Mitel calls to CRM tickets via:
    -- 1. Direct call_id match (primary, when call_id is not empty)
    -- 2. Phone number + time range (fallback within ±10 minutes using digits_dialed)
    SELECT DISTINCT ON (mc.call_id, mc.call_start_time)
        mc.call_id,
        mc.call_start_time,
        t.id as ticket_id,
        t.ticket_number,
        t.call_id as ticket_call_id,
        t.case_id as ticket_case_id,
        t.status as ticket_status,
        t.priority as ticket_priority,
        t.category as ticket_category,
        t.sub_category as ticket_subcategory,
        t.customer_rating as csat_score,
        t.resolved_at,
        t.customer_id,
        NULL::bigint as interaction_id,
        NULL::varchar as crm_disposition,
        t.description as crm_notes,
        CASE 
            WHEN mc.call_id != '' AND mc.call_id = t.call_id THEN 'call_id'
            ELSE 'phone_time'
        END as link_method
    FROM mitel_calls mc
    LEFT JOIN tickets t ON (
        -- Primary: Direct call_id match (only when call_id is not empty)
        (mc.call_id != '' AND mc.call_id = t.call_id)
        OR
        -- Fallback: Time range match
        (
            t.created_at BETWEEN mc.call_start_time - INTERVAL '10 minutes'
                             AND mc.call_start_time + INTERVAL '10 minutes'
            AND mc.disconnect_reason IS NOT NULL
            AND mc.disconnect_reason != ''
        )
    )
    WHERE t.deleted_at IS NULL OR t.id IS NULL
    ORDER BY mc.call_id, mc.call_start_time,
             CASE WHEN mc.call_id != '' AND mc.call_id = t.call_id THEN 1 ELSE 2 END,
             t.created_at ASC
)

SELECT
    -- === IDENTIFIERS ===
    mc.call_id as interaction_id,
    mc.call_id,
    lt.ticket_id,
    lt.ticket_number,
    lt.ticket_call_id,
    lt.ticket_case_id,
    
    -- === AGENT INFORMATION ===
    mc.mitel_agent_id,
    a.id as agent_id,
    u.name as agent_name,
    a.employee_id as agent_extension,
    a.department,
    
    -- === TIMESTAMPS ===
    mc.call_start_time as call_time,
    DATE(mc.call_start_time) as call_date,
    EXTRACT(HOUR FROM mc.call_start_time)::int as call_hour,
    
    -- === WRAP-UP CODE (FROM CRM IF LINKED) ===
    lt.crm_disposition as wrap_up_code,
    
    -- === WRAP-UP CATEGORY ===
    -- Categorize dispositions into meaningful groups
    CASE 
        -- Query/Information requests
        WHEN lt.crm_disposition IN ('Info Provided', 'Query Resolved', 'FAQ') 
        THEN 'Information Request'
        
        -- Technical Issues
        WHEN lt.crm_disposition IN ('Technical Support', 'System Error', 'Bug Report') 
        THEN 'Technical Issue'
        
        -- Service Requests
        WHEN lt.crm_disposition IN ('Service Request', 'Account Update', 'Registration') 
        THEN 'Service Request'
        
        -- Complaints
        WHEN lt.crm_disposition IN ('Complaint', 'Escalation', 'Manager Request') 
        THEN 'Complaint'
        
        -- Billing/Payment
        WHEN lt.crm_disposition IN ('Payment Issue', 'Billing Query', 'Refund') 
        THEN 'Billing'
        
        -- Abandoned/No Contact
        WHEN mc.call_state IN ('abandoned', 'caller_abandoned') 
        THEN 'No Contact'
        
        -- Follow-up needed
        WHEN lt.crm_disposition IN ('Callback Required', 'Follow-up Needed', 'Pending') 
        THEN 'Follow-up Required'
        
        -- Use ticket category if no CRM disposition
        WHEN lt.ticket_category IS NOT NULL THEN lt.ticket_category
        
        ELSE 'Other'
    END as wrap_up_category,
    
    -- === WRAP-UP NOTES ===
    lt.crm_notes as wrap_up_notes,
    CASE WHEN lt.crm_notes IS NOT NULL AND LENGTH(lt.crm_notes) > 0 THEN 1 ELSE 0 END as has_notes,
    
    -- === WRAP-UP TIME (ACW) ===
    mc.wrap_up_time_sec,
    TO_CHAR(INTERVAL '1 second' * mc.wrap_up_time_sec, 'MI:SS') as wrap_up_time_formatted,
    
    -- === ACW EFFICIENCY ===
    CASE 
        WHEN mc.wrap_up_time_sec = 0 THEN 'No ACW Recorded'
        WHEN mc.wrap_up_time_sec <= 30 THEN 'Efficient (≤30s)'
        WHEN mc.wrap_up_time_sec <= 60 THEN 'Good (31-60s)'
        WHEN mc.wrap_up_time_sec <= 120 THEN 'Fair (1-2m)'
        WHEN mc.wrap_up_time_sec <= 300 THEN 'Slow (2-5m)'
        ELSE 'Very Slow (>5m)'
    END as acw_efficiency,
    
    -- === CALL OUTCOME ===
    mc.call_state as call_status,
    CASE WHEN mc.call_state IN ('answered', 'connected', 'completed') THEN 1 ELSE 0 END as is_answered,
    
    -- === TICKET RESOLUTION (IF LINKED) ===
    lt.ticket_status,
    lt.ticket_priority,
    lt.ticket_category,
    lt.ticket_subcategory,
    
    -- Resolved within call?
    CASE 
        WHEN lt.ticket_status IN ('Resolved', 'Closed') 
         AND lt.resolved_at IS NOT NULL
         AND lt.resolved_at <= mc.call_start_time + INTERVAL '10 minutes'
        THEN 1 
        ELSE 0 
    END as resolved_during_call,
    
    -- === CALL METRICS ===
    mc.talk_time_sec,
    mc.wait_time_sec,
    
    -- Total handle time
    mc.talk_time_sec + mc.wrap_up_time_sec as total_handle_time_sec,
    
    -- === CUSTOMER INFORMATION ===
    lt.customer_id,
    c.name as customer_name,
    c.email as customer_email,
    
    -- === QUEUE INFORMATION ===
    mq.name as queue_name,
    
    -- === CUSTOMER SATISFACTION ===
    lt.csat_score as csat_score,
    CASE 
        WHEN lt.csat_score >= 4 THEN 'High (4-5)'
        WHEN lt.csat_score >= 3 THEN 'Medium (3)'
        WHEN lt.csat_score >= 1 THEN 'Low (1-2)'
        ELSE 'Not Rated'
    END as csat_category,
    
    -- === RESOLUTION TYPE ===
    CASE 
        WHEN lt.ticket_status IN ('Resolved', 'Closed') 
         AND lt.resolved_at IS NOT NULL
         AND lt.resolved_at <= mc.call_start_time + INTERVAL '24 hours'
        THEN 'First Contact Resolution (FCR)'
        
        WHEN lt.ticket_status IN ('Resolved', 'Closed') 
         AND lt.resolved_at IS NOT NULL
        THEN 'Resolved - Multiple Contacts'
        
        WHEN lt.ticket_status IN ('Open', 'In Progress')
        THEN 'In Progress'
        
        WHEN lt.ticket_status = 'Escalated'
        THEN 'Escalated'
        
        WHEN mc.call_state IN ('abandoned', 'caller_abandoned')
        THEN 'Abandoned - No Resolution'
        
        ELSE 'Other'
    END as resolution_type,
    
    -- === ISSUE TRENDING ===
    -- Priority + Category combination for trending
    COALESCE(lt.ticket_priority, 'N/A') || ' - ' || COALESCE(lt.ticket_category, 'N/A') as issue_classification,
    
    -- === DISCONNECT REASON ===
    mc.disconnect_reason,
    
    -- === DATA QUALITY FLAGS ===
    CASE 
        WHEN lt.crm_disposition IS NULL AND lt.ticket_id IS NULL THEN 'No CRM Link'
        WHEN mc.wrap_up_time_sec = 0 THEN 'No ACW Recorded'
        WHEN lt.ticket_id IS NULL THEN 'No Ticket Linked'
        ELSE 'Complete'
    END as data_quality_flag,
    
    -- === AUDIT FIELDS ===
    CURRENT_TIMESTAMP as record_created_at,
    CURRENT_TIMESTAMP as record_updated_at,
    1 as is_active

FROM mitel_calls mc
LEFT JOIN mitel_config_queues mq ON mc.queue_id::text = mq.queue_id::text
LEFT JOIN mitel_config_agents mca ON mc.mitel_agent_id::uuid = mca.fk_master_agent
LEFT JOIN agents a ON mca.fk_employee = a.employee_id::uuid
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN linked_tickets lt ON mc.call_id = lt.call_id
LEFT JOIN customers c ON lt.customer_id = c.id

WHERE mc.call_start_time >= CURRENT_DATE - INTERVAL '90 days'
;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Call reason distribution (Top 10 issues)
SELECT 
    wrap_up_category,
    COUNT(*) as call_count,
    ROUND(COUNT(*)::numeric * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM v_call_disposition
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY wrap_up_category
ORDER BY call_count DESC
LIMIT 10;

-- 2. Issue trend analysis (monthly)
SELECT 
    DATE_TRUNC('month', call_date) as month,
    wrap_up_category,
    COUNT(*) as issue_count
FROM v_call_disposition
WHERE call_date >= CURRENT_DATE - INTERVAL '12 months'
GROUP BY DATE_TRUNC('month', call_date), wrap_up_category
ORDER BY month DESC, issue_count DESC;

-- 3. Top complaint types
SELECT 
    ticket_category,
    ticket_subcategory,
    COUNT(*) as complaint_count,
    AVG(wrap_up_time_sec) as avg_acw_sec,
    AVG(total_handle_time_sec) as avg_handle_time_sec
FROM v_call_disposition
WHERE wrap_up_category = 'Complaint'
  AND call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY ticket_category, ticket_subcategory
ORDER BY complaint_count DESC
LIMIT 10;

-- 4. Ops insight - disposition by department
SELECT 
    department,
    wrap_up_category,
    COUNT(*) as call_count,
    AVG(wrap_up_time_sec) as avg_acw_sec,
    SUM(resolved_during_call) as fcr_count,
    ROUND(SUM(resolved_during_call)::numeric * 100.0 / COUNT(*), 2) as fcr_rate
FROM v_call_disposition
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY department, wrap_up_category
ORDER BY department, call_count DESC;

-- 5. ACW efficiency by wrap-up category
SELECT 
    wrap_up_category,
    acw_efficiency,
    COUNT(*) as call_count,
    AVG(wrap_up_time_sec) as avg_acw_sec
FROM v_call_disposition
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY wrap_up_category, acw_efficiency
ORDER BY wrap_up_category, 
    CASE acw_efficiency
        WHEN 'Efficient (≤30s)' THEN 1
        WHEN 'Good (31-60s)' THEN 2
        WHEN 'Fair (1-2m)' THEN 3
        WHEN 'Slow (2-5m)' THEN 4
        WHEN 'Very Slow (>5m)' THEN 5
        ELSE 6
    END;

-- 6. Resolution type distribution
SELECT 
    resolution_type,
    COUNT(*) as call_count,
    AVG(total_handle_time_sec) as avg_handle_time,
    AVG(CASE WHEN csat_score IS NOT NULL THEN csat_score END) as avg_csat
FROM v_call_disposition
WHERE call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY resolution_type
ORDER BY call_count DESC;

-- 7. Data quality dashboard
SELECT 
    data_quality_flag,
    COUNT(*) as record_count,
    ROUND(COUNT(*)::numeric * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM v_call_disposition
WHERE call_date >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY data_quality_flag
ORDER BY record_count DESC;

*/
