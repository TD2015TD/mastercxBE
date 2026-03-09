-- Comprehensive fix for all Mitel tables

-- Add updated_at and created_at to ALL lookup tables
ALTER TABLE mitel_lookup_day_of_week ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_lookup_day_of_week ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_lookup_time_interval ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_lookup_time_interval ADD COLUMN IF NOT EXISTS created_at timestamp(0);

-- Add updated_at and created_at to ALL config tables
ALTER TABLE mitel_config_agents ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_agents ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_config_queues ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_queues ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_config_dnis ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_dnis ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_config_account_codes ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_account_codes ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_config_ani ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_ani ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_config_make_busy ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_make_busy ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_config_agent_groups ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_agent_groups ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_config_extensions ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_extensions ADD COLUMN IF NOT EXISTS created_at timestamp(0);
ALTER TABLE mitel_config_trunks ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_config_trunks ADD COLUMN IF NOT EXISTS created_at timestamp(0);

-- Fix NOT NULL constraints on non-key columns
ALTER TABLE mitel_lookup_day_of_week ALTER COLUMN day_name DROP NOT NULL;
ALTER TABLE mitel_lookup_time_interval ALTER COLUMN interval_name DROP NOT NULL;
ALTER TABLE mitel_config_agents ALTER COLUMN name DROP NOT NULL;
ALTER TABLE mitel_config_queues ALTER COLUMN name DROP NOT NULL;
ALTER TABLE mitel_config_dnis ALTER COLUMN name DROP NOT NULL;
ALTER TABLE mitel_config_account_codes ALTER COLUMN name DROP NOT NULL;
ALTER TABLE mitel_config_ani ALTER COLUMN name DROP NOT NULL;
ALTER TABLE mitel_config_make_busy ALTER COLUMN name DROP NOT NULL;
ALTER TABLE mitel_config_agent_groups ALTER COLUMN name DROP NOT NULL;
ALTER TABLE mitel_config_extensions ALTER COLUMN name DROP NOT NULL;
ALTER TABLE mitel_config_trunks ALTER COLUMN name DROP NOT NULL;

-- Data tables
ALTER TABLE mitel_data_agent_performance ALTER COLUMN agent_id DROP NOT NULL;
ALTER TABLE mitel_data_agent_performance ALTER COLUMN period_date DROP NOT NULL;
ALTER TABLE mitel_data_agent_performance ALTER COLUMN acd_count DROP NOT NULL;
ALTER TABLE mitel_data_agent_performance ALTER COLUMN acd_duration DROP NOT NULL;
ALTER TABLE mitel_data_agent_performance ALTER COLUMN shift_duration DROP NOT NULL;
ALTER TABLE mitel_data_agent_performance ALTER COLUMN idle_duration DROP NOT NULL;
ALTER TABLE mitel_data_agent_performance ALTER COLUMN make_busy_count DROP NOT NULL;
ALTER TABLE mitel_data_agent_performance ALTER COLUMN make_busy_duration DROP NOT NULL;

ALTER TABLE mitel_data_queue_performance ALTER COLUMN queue_id DROP NOT NULL;
ALTER TABLE mitel_data_queue_performance ALTER COLUMN period_date DROP NOT NULL;
ALTER TABLE mitel_data_queue_performance ALTER COLUMN acd_count DROP NOT NULL;
ALTER TABLE mitel_data_queue_performance ALTER COLUMN acd_duration DROP NOT NULL;
ALTER TABLE mitel_data_queue_performance ALTER COLUMN abandon_count DROP NOT NULL;
ALTER TABLE mitel_data_queue_performance ALTER COLUMN service_level DROP NOT NULL;
ALTER TABLE mitel_data_queue_performance ALTER COLUMN max_waiting_calls DROP NOT NULL;

ALTER TABLE mitel_data_inbound_trace ALTER COLUMN call_date DROP NOT NULL;
ALTER TABLE mitel_data_inbound_trace ALTER COLUMN duration DROP NOT NULL;
ALTER TABLE mitel_data_inbound_trace ALTER COLUMN time_to_answer DROP NOT NULL;

ALTER TABLE mitel_data_outbound_trace ALTER COLUMN call_date DROP NOT NULL;
ALTER TABLE mitel_data_outbound_trace ALTER COLUMN duration DROP NOT NULL;

ALTER TABLE mitel_data_agent_trace_events ALTER COLUMN agent_id DROP NOT NULL;
ALTER TABLE mitel_data_agent_trace_events ALTER COLUMN event_start_date DROP NOT NULL;

ALTER TABLE mitel_data_agent_events ALTER COLUMN agent_id DROP NOT NULL;
ALTER TABLE mitel_data_agent_events ALTER COLUMN event_date DROP NOT NULL;
ALTER TABLE mitel_data_agent_events ALTER COLUMN duration DROP NOT NULL;

-- Add missing updated_at and created_at columns to ALL data tables
ALTER TABLE mitel_data_agent_performance ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_data_agent_performance ADD COLUMN IF NOT EXISTS created_at timestamp(0);

ALTER TABLE mitel_data_queue_performance ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_data_queue_performance ADD COLUMN IF NOT EXISTS created_at timestamp(0);

ALTER TABLE mitel_data_inbound_trace ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_data_inbound_trace ADD COLUMN IF NOT EXISTS created_at timestamp(0);

ALTER TABLE mitel_data_outbound_trace ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_data_outbound_trace ADD COLUMN IF NOT EXISTS created_at timestamp(0);

ALTER TABLE mitel_data_agent_trace_events ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_data_agent_trace_events ADD COLUMN IF NOT EXISTS created_at timestamp(0);

ALTER TABLE mitel_data_agent_events ADD COLUMN IF NOT EXISTS updated_at timestamp(0);
ALTER TABLE mitel_data_agent_events ADD COLUMN IF NOT EXISTS created_at timestamp(0);

-- Drop foreign key constraints to allow historical data with deleted/missing agents
ALTER TABLE mitel_data_agent_performance DROP CONSTRAINT IF EXISTS mitel_data_agent_performance_agent_id_foreign;
ALTER TABLE mitel_data_agent_performance DROP CONSTRAINT IF EXISTS mitel_data_agent_performance_day_of_week_id_foreign;
ALTER TABLE mitel_data_agent_performance DROP CONSTRAINT IF EXISTS mitel_data_agent_performance_time_interval_id_foreign;

ALTER TABLE mitel_data_queue_performance DROP CONSTRAINT IF EXISTS mitel_data_queue_performance_queue_id_foreign;
ALTER TABLE mitel_data_queue_performance DROP CONSTRAINT IF EXISTS mitel_data_queue_performance_day_of_week_id_foreign;
ALTER TABLE mitel_data_queue_performance DROP CONSTRAINT IF EXISTS mitel_data_queue_performance_time_interval_id_foreign;

ALTER TABLE mitel_data_inbound_trace DROP CONSTRAINT IF EXISTS mitel_data_inbound_trace_agent_id_foreign;
ALTER TABLE mitel_data_inbound_trace DROP CONSTRAINT IF EXISTS mitel_data_inbound_trace_queue_id_foreign;
ALTER TABLE mitel_data_inbound_trace DROP CONSTRAINT IF EXISTS mitel_data_inbound_trace_dnis_id_foreign;
ALTER TABLE mitel_data_inbound_trace DROP CONSTRAINT IF EXISTS mitel_data_inbound_trace_ani_id_foreign;

ALTER TABLE mitel_data_outbound_trace DROP CONSTRAINT IF EXISTS mitel_data_outbound_trace_agent_id_foreign;
ALTER TABLE mitel_data_outbound_trace DROP CONSTRAINT IF EXISTS mitel_data_outbound_trace_account_code_id_foreign;

ALTER TABLE mitel_data_agent_trace_events DROP CONSTRAINT IF EXISTS mitel_data_agent_trace_events_agent_id_foreign;
ALTER TABLE mitel_data_agent_trace_events DROP CONSTRAINT IF EXISTS mitel_data_agent_trace_events_make_busy_id_foreign;
ALTER TABLE mitel_data_agent_trace_events DROP CONSTRAINT IF EXISTS mitel_data_agent_trace_events_queue_id_foreign;

ALTER TABLE mitel_data_agent_events DROP CONSTRAINT IF EXISTS mitel_data_agent_events_agent_id_foreign;
