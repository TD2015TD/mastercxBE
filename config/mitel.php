<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mitel CCMData Sync Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for syncing data from Mitel CCMData
    | database. Define table mappings, sync schedules, and transformation rules.
    |
    */

    /**
     * Default sync type: 'full' or 'incremental'
     */
    'default_sync_type' => env('MITEL_SYNC_TYPE', 'incremental'),

    /**
     * Cron schedule expression for automatic syncs
     * Examples:
     * - '* * * * *' : Every minute
     * - '0 * * * *' : Every hour
     * - '0 0 * * *' : Daily at midnight
     * - 'star/15 * * * *' : Every 15 minutes (replace star with *)
     */
    'sync_schedule' => env('MITEL_SYNC_SCHEDULE', '0 * * * *'), // Every hour by default

    /**
     * Tables to sync from CCMData database
     * Prioritized: tblData_* tables and their lookup/config dependencies
     */
    'sync_tables' => [
        // Priority 1: Core lookup tables (required for tblData foreign keys)
        'tblLookup_DayOfWeek',
        'tblLookup_TimeInterval',
        'tblLookup_CallCosting_CallType',
        'tblLookup_AgentTraceEvents',
        'tblLookup_DeviceType',
        'tblLookup_NodeFamily',
        
        // Priority 2: Configuration tables (referenced by tblData)
        'tblConfig_Agent',
        'tblConfig_AgentGroup',
        'tblConfig_Queue',
        'tblConfig_DNIS',
        'tblConfig_Extension',
        'tblConfig_Trunk',
        'tblConfig_AccountCode',
        'tblConfig_ANI',
        'tblConfig_MakeBusy',
        
        // Priority 3: High-value tblData tables
        'tblData_AgentPerformanceByPeriod',
        'tblData_QueuePerformanceByPeriod',
        'tblData_AgentEvents',
        'tblData_InboundTrace',
        'tblData_OutboundTrace',
        'tblData_AgentTraceEvents',
        'tblData_CA_Trace',
        
        // Priority 4: Queue and device analytics
        'tblData_QueueByANIByDNIS',
        'tblData_DeviceByQueue',
        'tblData_DeviceByDNIS',
        'tblData_DNISPerformanceByPeriod',
        
        // Priority 5: Agent-focused data
        'tblData_AgentByDevice',
        'tblData_AgentByAccountCode',
        'tblData_AgentByMakeBusyCode',
        
        // Priority 6: Extended performance data
        'tblData_AccountCodePerformanceByPeriod',
        'tblData_ExtensionPerformanceByPeriod',
        'tblData_TrunkPerformanceByPeriod',
        
        // Priority 7: Multi-channel data
        'tblData_Chat_AgentLogInInterval',
        'tblData_Chat_QueueIntervalPerformance',
        'tblData_EMail_AgentsLogInInterval',
        'tblData_EMail_QueueIntervalPerformance',
        
        // Add more tables as needed
    ],

    /**
     * Table mappings: Define how data from CCMData tables should be mapped
     * to your local database tables.
     */
    'table_mappings' => [
        
        // Lookup Tables
        'tblLookup_DayOfWeek' => [
            'source_table' => 'tblLookup_DayOfWeek',
            'target_table' => 'mitel_lookup_day_of_week',
            'unique_key' => 'Pkey',
            'column_map' => [
                'Pkey' => 'id',
                'DayName' => 'day_name',
            ],
        ],

        'tblLookup_TimeInterval' => [
            'source_table' => 'tblLookup_TimeInterval',
            'target_table' => 'mitel_lookup_time_interval',
            'unique_key' => 'Pkey',
            'column_map' => [
                'Pkey' => 'id',
                'IntervalName' => 'interval_name',
            ],
        ],

        // Config Tables
        'tblConfig_Agent' => [
            'source_table' => 'tblConfig_Agent',
            'target_table' => 'mitel_config_agents',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'agent_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'Dialable' => 'dialable',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        'tblConfig_Queue' => [
            'source_table' => 'tblConfig_Queue',
            'target_table' => 'mitel_config_queues',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'queue_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        'tblConfig_DNIS' => [
            'source_table' => 'tblConfig_DNIS',
            'target_table' => 'mitel_config_dnis',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'dnis_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'PhoneNumber' => 'phone_number',
                'Service_Level' => 'service_level',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        'tblConfig_AccountCode' => [
            'source_table' => 'tblConfig_AccountCode',
            'target_table' => 'mitel_config_account_codes',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'account_code_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'AccountCode' => 'account_code',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        'tblConfig_ANI' => [
            'source_table' => 'tblConfig_ANI',
            'target_table' => 'mitel_config_ani',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'ani_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'PhoneNumber' => 'phone_number',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        'tblConfig_MakeBusy' => [
            'source_table' => 'tblConfig_MakeBusy',
            'target_table' => 'mitel_config_make_busy',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'make_busy_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'ReasonCode' => 'reason_code',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        'tblConfig_AgentGroup' => [
            'source_table' => 'tblConfig_AgentGroup',
            'target_table' => 'mitel_config_agent_groups',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'agent_group_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        'tblConfig_Extension' => [
            'source_table' => 'tblConfig_Extension',
            'target_table' => 'mitel_config_extensions',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'extension_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'Extension' => 'extension_number',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        'tblConfig_Trunk' => [
            'source_table' => 'tblConfig_Trunk',
            'target_table' => 'mitel_config_trunks',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'ModifiedOn',
            'column_map' => [
                'Pkey' => 'trunk_id',
                'Name' => 'name',
                'Reporting' => 'reporting_id',
                'CreatedOn' => 'created_at',
                'ModifiedOn' => 'updated_at',
            ],
        ],

        // tblData Tables
        'tblData_AgentPerformanceByPeriod' => [
            'source_table' => 'tblData_AgentPerformanceByPeriod',
            'target_table' => 'mitel_data_agent_performance',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'MidnightStartDate',
            'column_map' => [
                'Pkey' => 'id',
                'FKAgent' => 'agent_id',
                'MidnightStartDate' => 'period_date',
                'FKDayOfWeek' => 'day_of_week_id',
                'FKTimeInterval' => 'time_interval_id',
                'ACDCount' => 'acd_count',
                'ACDDuration' => 'acd_duration',
                'ShiftDuration' => 'shift_duration',
                'IdleDuration' => 'idle_duration',
                'MakeBusyCount' => 'make_busy_count',
                'MakeBusyDuration' => 'make_busy_duration',
            ],
        ],

        'tblData_QueuePerformanceByPeriod' => [
            'source_table' => 'tblData_QueuePerformanceByPeriod',
            'target_table' => 'mitel_data_queue_performance',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'MidnightStartDate',
            'column_map' => [
                'Pkey' => 'id',
                'FKQueue' => 'queue_id',
                'MidnightStartDate' => 'period_date',
                'FKDayOfWeek' => 'day_of_week_id',
                'FKTimeInterval' => 'time_interval_id',
                'ACDCount' => 'acd_count',
                'ACDDuration' => 'acd_duration',
                'AbandonCount' => 'abandon_count',
                'ServiceLevel' => 'service_level',
                'MaxWaitingCalls' => 'max_waiting_calls',
            ],
        ],

        'tblData_InboundTrace' => [
            'source_table' => 'tblData_InboundTrace',
            'target_table' => 'mitel_data_inbound_trace',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'MidnightStartDate',
            'column_map' => [
                'Pkey' => 'id',
                'FKAgent' => 'agent_id',
                'FKQueue' => 'queue_id',
                'FKDNIS' => 'dnis_id',
                'FKANI' => 'ani_id',
                'MidnightStartDate' => 'call_date',
                'Duration' => 'duration',
                'TimeToAnswer' => 'time_to_answer',
                'DigitsDialed' => 'digits_dialed',
                'CallId' => 'call_id',
            ],
        ],

        'tblData_AgentTraceEvents' => [
            'source_table' => 'tblData_AgentTraceEvents',
            'target_table' => 'mitel_data_agent_trace_events',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'MidnightStartDate',
            'column_map' => [
                'Pkey' => 'id',
                'FKAgent' => 'agent_id',
                'MidnightStartDate' => 'event_start_date',
                'MidnightEndDate' => 'event_end_date',
                'FKAcdEvent' => 'acd_event_id',
                'FKMakeBusy' => 'make_busy_id',
                'FKQueue' => 'queue_id',
            ],
        ],

        'tblData_OutboundTrace' => [
            'source_table' => 'tblData_OutboundTrace',
            'target_table' => 'mitel_data_outbound_trace',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'MidnightStartDate',
            'column_map' => [
                'Pkey' => 'id',
                'FKAgent' => 'agent_id',
                'FKAccountCode' => 'account_code_id',
                'MidnightStartDate' => 'call_date',
                'Duration' => 'duration',
                'DigitsDialed' => 'digits_dialed',
                'CallId' => 'call_id',
            ],
        ],

        'tblData_AgentEvents' => [
            'source_table' => 'tblData_AgentEvents',
            'target_table' => 'mitel_data_agent_events',
            'unique_key' => 'Pkey',
            'timestamp_column' => 'MidnightStartDate',
            'column_map' => [
                'Pkey' => 'id',
                'FKAgent' => 'agent_id',
                'MidnightStartDate' => 'event_date',
                'EventType' => 'event_type',
                'Duration' => 'duration',
            ],
        ],

        // Add more mappings as needed...
    ],

    /**
     * Sync options
     */
    'options' => [
        'chunk_size' => 1000, // Number of records to process at a time
        'timeout' => 300, // Timeout in seconds
        'retry_attempts' => 3, // Number of retry attempts on failure
        'retry_delay' => 5, // Delay between retries in seconds
    ],

    /**
     * Logging options
     */
    'logging' => [
        'enabled' => true,
        'channel' => env('MITEL_LOG_CHANNEL', 'single'),
        'level' => env('MITEL_LOG_LEVEL', 'info'),
    ],

    /**
     * Alert configuration
     */
    'alerts' => [
        'enabled' => env('MITEL_ALERTS_ENABLED', false),
        'email' => env('MITEL_ALERT_EMAIL'),
        'on_failure' => true,
        'on_success' => false,
    ],
];
