CREATE VIEW ticketing_report AS
SELECT
    id AS ticket_id,
    ticket_number,
    subject,
    status,
    priority,
    department,
    category,
    sub_category,
    assigned_to,
    created_by,
    created_at,
    resolved_at,
    closed_at,
    sla_breached,
    sla_due_at,
    sla_remaining_minutes,
    first_response_at,
    first_response_time_minutes,
    resolution_time_minutes,
    channel,
    source,
    customer_rating,
    reopened_count
FROM tickets
WHERE deleted_at IS NULL;
