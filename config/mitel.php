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
        'tblLookup_Weekday',
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
        'tblConfig_MakeBusyReasonCode',
        
        // Priority 3: High-value tblData tables and views
        'AgentPerformanceByPeriodStats', // View: Most comprehensive agent performance metrics (50+ columns)
        'tblData_AgentPerformanceByPeriod',
        'tblData_QueuePerformanceByPeriod',
        'tblData_AgentEvents',
        'tblData_InboundTrace',
        'tblData_OutboundTrace',
        'tblData_AgentTraceEvents',
        'tblData_CA_Trace',
        'AgentInboundCallDetails', // View: Enhanced inbound call details for reporting
        'AgentOutboundCallDetails', // View: Enhanced outbound call details for reporting
        
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
        'tblLookup_Weekday' => [
            'source_table' => 'tblLookup_Weekday',
            'target_table' => 'mitel_lookup_day_of_week',
            'unique_key' => 'Pkey',
            'column_map' => [
                'Pkey' => 'id',
                'Weekday' => 'day_name',
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
            // Use custom query with JOIN to get employee data
            'custom_query' => "SELECT 
                a.Pkey,
                a.FKEmployee,
                a.FKMasterAgent,
                a.FirstName,
                a.LastName,
                a.Reporting,
                a.Auto_Programmed,
                a.isActive,
                a.ExistsOnPBX,
                a.Last_Modified,
                a.FKSite,
                a.FKNode,
                a.FKFailOver,
                a.CreatedOn,
                a.CreatedBy,
                a.ModifiedOn,
                a.ModifiedBy,
                a.CreatedByClientId,
                a.ModifiedByClientId,
                -- Employee fields (all ISNULL to handle LEFT JOIN nulls)
                ISNULL(e.Reporting, '') AS EmployeeReporting,
                ISNULL(e.FirstName, '') AS EmployeeFirstName,
                ISNULL(e.LastName, '') AS EmployeeLastName,
                ISNULL(e.MiddleName, '') AS MiddleName,
                ISNULL(e.Suffix, '') AS Suffix,
                ISNULL(e.NickName, '') AS NickName,
                ISNULL(e.EmailAddress, '') AS EmailAddress,
                ISNULL(e.Phone, '') AS Phone,
                ISNULL(e.BusinessPhone, '') AS BusinessPhone,
                ISNULL(e.Fax, '') AS Fax,
                ISNULL(e.Pager, '') AS Pager,
                ISNULL(e.StreetAddress, '') AS StreetAddress,
                ISNULL(e.City, '') AS City,
                ISNULL(e.StateProvince, '') AS StateProvince,
                ISNULL(e.ZipPostal, '') AS ZipPostal,
                e.Country,
                e.Active_Date,
                e.BirthDate,
                ISNULL(e.IsActive, 0) AS EmployeeIsActive,
                ISNULL(e.IsScheduled, 0) AS IsScheduled,
                ISNULL(e.IsSupervisor, 0) AS IsSupervisor,
                e.SupervisorID,
                ISNULL(e.ECName, '') AS ECName,
                ISNULL(e.ECRelationship, '') AS ECRelationship,
                ISNULL(e.ECPhone, '') AS ECPhone,
                ISNULL(e.AssociatedIdentity, '') AS AssociatedIdentity,
                ISNULL(e.ExistsInAD, 0) AS ExistsInAD,
                ISNULL(e.TelephonyEndPointType, 0) AS TelephonyEndPointType,
                ISNULL(e.SupervisorLicenseType, 0) AS SupervisorLicenseType,
                ISNULL(e.HasLicenseForMCC, 0) AS HasLicenseForMCC,
                ISNULL(e.HasLicenseForVoice, 0) AS HasLicenseForVoice,
                ISNULL(e.HasLicenseForScreenPop, 0) AS HasLicenseForScreenPop,
                ISNULL(e.PhotoUrl, '') AS PhotoUrl,
                ISNULL(e.SipAddress, '') AS SipAddress,
                ISNULL(e.LineURI, '') AS LineURI,
                ISNULL(e.CloudlinkId, '') AS CloudlinkId,
                ISNULL(e.CloudLinkChatEnabled, 0) AS CloudLinkChatEnabled,
                ISNULL(e.IsMaySeeAllQueuesEnabled, 0) AS IsMaySeeAllQueuesEnabled
            FROM tblConfig_Agent a WITH (NOLOCK)
            LEFT JOIN tblConfig_Employee e WITH (NOLOCK) ON CAST(a.FKEmployee AS VARCHAR(36)) = CAST(e.Pkey AS VARCHAR(36))
            WHERE a.ModifiedOn > CAST(? AS DATETIME)",
            'column_map' => [
                // Agent fields
                'Pkey' => 'pkey',
                'FKEmployee' => 'fk_employee',
                'FKMasterAgent' => 'fk_master_agent',
                'FirstName' => 'first_name',
                'LastName' => 'last_name',
                'Reporting' => 'reporting',
                'Auto_Programmed' => 'auto_programmed',
                'isActive' => 'is_active',
                'ExistsOnPBX' => 'exists_on_pbx',
                'Last_Modified' => 'last_modified',
                'FKSite' => 'fk_site',
                'FKNode' => 'fk_node',
                'FKFailOver' => 'fk_fail_over',
                // Employee fields
                'EmployeeReporting' => 'employee_reporting_id',
                'EmployeeFirstName' => 'employee_first_name',
                'EmployeeLastName' => 'employee_last_name',
                'MiddleName' => 'middle_name',
                'Suffix' => 'suffix',
                'NickName' => 'nick_name',
                'EmailAddress' => 'email_address',
                'Phone' => 'phone',
                'BusinessPhone' => 'business_phone',
                'Fax' => 'fax',
                'Pager' => 'pager',
                'StreetAddress' => 'street_address',
                'City' => 'city',
                'StateProvince' => 'state_province',
                'ZipPostal' => 'zip_postal',
                'Country' => 'country_id',
                'Active_Date' => 'employee_active_date',
                'BirthDate' => 'birth_date',
                'EmployeeIsActive' => 'employee_is_active',
                'IsScheduled' => 'is_scheduled',
                'IsSupervisor' => 'is_supervisor',
                'SupervisorID' => 'supervisor_id',
                'ECName' => 'emergency_contact_name',
                'ECRelationship' => 'emergency_contact_relationship',
                'ECPhone' => 'emergency_contact_phone',
                'AssociatedIdentity' => 'associated_identity',
                'ExistsInAD' => 'exists_in_ad',
                'TelephonyEndPointType' => 'telephony_endpoint_type',
                'SupervisorLicenseType' => 'supervisor_license_type',
                'HasLicenseForMCC' => 'has_license_for_mcc',
                'HasLicenseForVoice' => 'has_license_for_voice',
                'HasLicenseForScreenPop' => 'has_license_for_screen_pop',
                'PhotoUrl' => 'photo_url',
                'SipAddress' => 'sip_address',
                'LineURI' => 'line_uri',
                'CloudlinkId' => 'cloudlink_id',
                'CloudLinkChatEnabled' => 'cloudlink_chat_enabled',
                'IsMaySeeAllQueuesEnabled' => 'is_may_see_all_queues_enabled',
                // Audit
                'CreatedOn' => 'created_on',
                'ModifiedOn' => 'modified_on',
            ],
        ],

        'AgentPerformanceByPeriodStats' => [
            'source_table' => 'AgentPerformanceByPeriodStats',
            'target_table' => 'mitel_agent_performance_by_period',
            'unique_key' => ['agent_id', 'interval_start_time'], // Use TRANSFORMED column names
            'timestamp_column' => 'IntervalStartTime',
            'column_map' => [
                // Key columns first (must be exact for composite key matching)
                'AgentID' => 'agent_id',
                'EmployeeID' => 'employee_id',
                'MidnightStartDate' => 'midnight_start_date',
                
                // Date/Time fields - need special handling
                'IntervalStartTime' => 'interval_start_time_raw', // Time part only - will be transformed
                
                // Agent identification
                'AgentReporting' => 'agent_reporting',
                
                // Time/Date fields
                'DayOfWeekName' => 'day_of_week_name',
                'TimeIntervalID' => 'interval_number',
                
                // ACD metrics - match migration column names exactly
                'AgentACDCount' => 'agent_acd_count',
                'AgentACDDuration' => 'agent_acd_duration',
                'AgentACDTimeToAnswer' => 'agent_acd_time_to_answer',
                
                // ACD Internal/External
                'AgentInternalACDCount' => 'agent_acd_internal_count',
                'AgentInternalACDDuration' => 'agent_acd_internal_duration',
                'AgentExternalACDCount' => 'agent_acd_external_count',
                'AgentExternalACDDuration' => 'agent_acd_external_duration',
                
                // Non-ACD metrics
                'AgentNonACDCount' => 'agent_non_acd_count',
                'AgentNonACDDuration' => 'agent_non_acd_duration',
                'AgentNonACDTimeToAnswer' => 'agent_non_acd_time_to_answer',
                
                // Non-ACD Internal/External
                'AgentInternalNonACDCount' => 'agent_non_acd_internal_count',
                'AgentInternalNonACDDuration' => 'agent_non_acd_internal_duration',
                'AgentExternalNonACDCount' => 'agent_non_acd_external_count',
                'AgentExternalNonACDDuration' => 'agent_non_acd_external_duration',
                
                // Outbound metrics
                'AgentOutboundCount' => 'agent_outbound_count',
                'AgentOutboundDuration' => 'agent_outbound_duration',
                
                // Outbound Internal/External
                'AgentInternalOutboundCount' => 'agent_outbound_internal_count',
                'AgentInternalOutboundDuration' => 'agent_outbound_internal_duration',
                'AgentExternalOutboundCount' => 'agent_outbound_external_count',
                'AgentExternalOutboundDuration' => 'agent_outbound_external_duration',
                
                // Transfer and conference - match migration exactly
                'AgentTransferCount' => 'agent_transfer_count',
                'AgentConferenceCount' => 'agent_conference_count',
                'AgentAbandonCount' => 'agent_abandoned_count', // Note: "abandoned" not "abandon"
            ],
            'transformations' => [
                // Combine MidnightStartDate + IntervalStartTime_raw to create full timestamp
                'interval_start_time' => function ($data) {
                    if (isset($data['midnight_start_date']) && isset($data['interval_start_time_raw'])) {
                        $dateValue = $data['midnight_start_date'];
                        $time = trim($data['interval_start_time_raw']); // Trim whitespace from nchar(5)
                        
                        // Extract just date part (YYYY-MM-DD) from datetime
                        if ($dateValue instanceof \DateTime) {
                            $date = $dateValue->format('Y-m-d');
                        } elseif (is_string($dateValue)) {
                            // String like "2025-05-28 14:30:00.000" - extract first 10 chars
                            $date = substr($dateValue, 0, 10);
                        } else {
                            $date = (string) $dateValue;
                            $date = substr($date, 0, 10);
                        }
                        
                        // Combine date and time into PostgreSQL timestamp format
                        return $date . ' ' . $time . ':00'; // Result: "2025-05-28 14:30:00"
                    }
                    return null;
                },
            ],
            'cleanup_columns' => ['interval_start_time_raw'], // Remove helper column before insert
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

        'tblConfig_MakeBusyReasonCode' => [
            'source_table' => 'tblConfig_MakeBusyReasonCode',
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

        // View: AgentInboundCallDetails - Comprehensive inbound call reporting data
        'AgentInboundCallDetails' => [
            'source_table' => 'AgentInboundCallDetails',
            'target_table' => 'mitel_agent_inbound_call_details',
            'unique_key' => 'ExtensionID', // Using ExtensionID as unique identifier (appears to be unique per call)
            'timestamp_column' => 'MidnightStartDateTime',
            'column_map' => [
                'AgentID' => 'agent_id',
                'AgentReporting' => 'agent_reporting',
                'AgentFirstName' => 'agent_first_name',
                'AgentLastName' => 'agent_last_name',
                'ExtensionID' => 'extension_id',
                'ExtensionReporting' => 'extension_reporting',
                'ExtensionName' => 'extension_name',
                'TrunkID' => 'trunk_id',
                'TrunkReporting' => 'trunk_reporting',
                'TrunkName' => 'trunk_name',
                'QueueID' => 'queue_id',
                'QueueReporting' => 'queue_reporting',
                'QueueName' => 'queue_name',
                'AgentGroupID' => 'agent_group_id',
                'AgentGroupReporting' => 'agent_group_reporting',
                'AgentGroupName' => 'agent_group_name',
                'DnisID' => 'dnis_id',
                'DnisReporting' => 'dnis_reporting',
                'DnisName' => 'dnis_name',
                'AccountCodeID' => 'account_code_id',
                'AccountCodeReporting' => 'account_code_reporting',
                'AccountCodeName' => 'account_code_name',
                'ANIID' => 'ani_id',
                'ANIReporting' => 'ani_reporting',
                'ANIName' => 'ani_name',
                'MidnightStartDateTime' => 'midnight_start_datetime',
                'DateOfCall' => 'date_of_call',
                'CallStartTime' => 'call_start_time',
                'CallType' => 'call_type',
                'CallTypeNumber' => 'call_type_number',
                'Duration' => 'duration',
                'TimeToAnswer' => 'time_to_answer',
                'DigitsDialed' => 'digits_dialed',
                'TransferConferenceNumber' => 'transfer_conference_number',
                'TransferConference' => 'transfer_conference',
            ],
        ],

        // View: AgentOutboundCallDetails - Comprehensive outbound call reporting data
        'AgentOutboundCallDetails' => [
            'source_table' => 'AgentOutboundCallDetails',
            'target_table' => 'mitel_agent_outbound_call_details',
            'unique_key' => 'ExtensionID', // Using ExtensionID as unique identifier
            'timestamp_column' => 'MidnightStartDateTime',
            'column_map' => [
                'AgentID' => 'agent_id',
                'AgentReporting' => 'agent_reporting',
                'AgentFirstName' => 'agent_first_name',
                'AgentLastName' => 'agent_last_name',
                'ExtensionID' => 'extension_id',
                'ExtensionReporting' => 'extension_reporting',
                'ExtensionName' => 'extension_name',
                'TrunkID' => 'trunk_id',
                'TrunkReporting' => 'trunk_reporting',
                'TrunkName' => 'trunk_name',
                'AccountCodeID' => 'account_code_id',
                'AccountCodeReporting' => 'account_code_reporting',
                'AccountCodeName' => 'account_code_name',
                'MidnightStartDateTime' => 'midnight_start_datetime',
                'DateOfCall' => 'date_of_call',
                'CallStartTime' => 'call_start_time',
                'CallType' => 'call_type',
                'CallTypeNumber' => 'call_type_number',
                'Duration' => 'duration',
                'PhoneNumber' => 'phone_number',
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
