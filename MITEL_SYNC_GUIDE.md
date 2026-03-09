# Mitel CCMData Sync Service

This service provides automated data synchronization from Mitel CCMData SQL Server database to the CRM PostgreSQL database. Connections are stored in the database for easy management.

## Overview

The sync service consists of:
- **MitelConnection Model**: Stores database connection configurations (encrypted passwords)
- **MitelSyncLog Model**: Tracks sync history and statistics
- **MitelDataSyncService**: Handles the actual data synchronization logic
- **SyncMitelData Command**: Artisan command for manual and scheduled syncs
- **Cron Scheduler**: Automatic syncs based on configured schedule

## Database Schema

### Connection Configuration (mitel_connections)
```
jdbc:sqlserver://;serverName=10.0.0.84;databaseName=CCMData
```

Stores:
- Connection details (host, port, database, credentials)
- Encryption settings
- Active status
- Last sync information

### Sync Logs (mitel_sync_logs)
Tracks each sync operation with:
- Records fetched/inserted/updated/failed
- Duration and timestamps
- Error messages
- Sync type (full/incremental)

## Setup Instructions

### 1. Run Migrations

```bash
cd /Users/mohdmarzuqikrammohdhelmi/Workspace/jkdm-crm/crm-api
php artisan migrate
```

This creates:
- `mitel_connections` - Connection configurations
- `mitel_sync_logs` - Sync history tracking
- `mitel_agents` - Example synced data table

### 2. Create Initial Connection

Option A: Use the seeder (update credentials first):

```bash
# Edit database/seeders/MitelConnectionSeeder.php with your credentials
php artisan db:seed --class=MitelConnectionSeeder
```

Option B: Create manually via database or API:

```php
use App\Models\MitelConnection;

MitelConnection::create([
    'name' => 'Primary CCMData',
    'description' => 'Main Mitel CCMData database',
    'host' => '10.0.0.84',
    'port' => 1433,
    'database' => 'CCMData',
    'username' => 'your_username',
    'password' => 'your_password', // Will be encrypted automatically
    'encrypt' => true,
    'trust_server_certificate' => true,
    'is_active' => true,
]);
```

### 3. Configure Sync Tables

Edit `config/mitel.php` to define which tables to sync and their mappings:

```php
'sync_tables' => [
    'tblConfig_Agent',
    'tblConfig_AgentGroup',
    'tblReal_Agent',
    // Add more tables...
],

'table_mappings' => [
    'tblConfig_Agent' => [
        'source_table' => 'tblConfig_Agent',
        'target_table' => 'mitel_agents',
        'unique_key' => 'Pkey',
        'timestamp_column' => 'ModifiedOn',
        'column_map' => [
            'Pkey' => 'agent_id',
            'Name' => 'name',
            // ... more mappings
        ],
    ],
],
```

### 4. Create Target Tables

For each table you want to sync, create a migration:

```bash
php artisan make:migration create_mitel_[table_name]_table
```

Define the schema to match your mapped columns.

## Usage

### Test Connection

```bash
php artisan mitel:sync "Primary CCMData" --test
```

### Manual Sync

Sync specific connection:
```bash
php artisan mitel:sync "Primary CCMData"
```

Sync all active connections:
```bash
php artisan mitel:sync --all
```

Sync specific tables only:
```bash
php artisan mitel:sync "Primary CCMData" --tables=tblConfig_Agent --tables=tblReal_Agent
```

Incremental sync (only changed records):
```bash
php artisan mitel:sync "Primary CCMData" --type=incremental
```

Full sync (all records):
```bash
php artisan mitel:sync "Primary CCMData" --type=full
```

### Automatic Scheduled Sync

The sync runs automatically based on the cron schedule defined in `config/mitel.php`:

```php
'sync_schedule' => env('MITEL_SYNC_SCHEDULE', '0 * * * *'), // Every hour
```

To modify the schedule, set in `.env`:
```env
MITEL_SYNC_SCHEDULE="*/15 * * * *"  # Every 15 minutes
```

Or edit `app/Console/Kernel.php` directly.

### Monitor Sync Status

Check connection status:
```php
$connection = MitelConnection::find(1);
echo $connection->sync_status; // 'success', 'failed', 'running'
echo $connection->last_sync_at;
echo $connection->last_sync_message;
```

View sync logs:
```php
$logs = MitelSyncLog::where('mitel_connection_id', 1)
    ->orderBy('started_at', 'desc')
    ->get();

foreach ($logs as $log) {
    echo "Status: {$log->status}\n";
    echo "Fetched: {$log->records_fetched}\n";
    echo "Inserted: {$log->records_inserted}\n";
    echo "Updated: {$log->records_updated}\n";
    echo "Duration: {$log->duration} seconds\n";
}
```

## Configuration

### Environment Variables

Add to `.env` (optional, since connections are stored in database):

```env
# Mitel Sync Configuration
MITEL_SYNC_SCHEDULE="0 * * * *"
MITEL_SYNC_TYPE=incremental
MITEL_LOG_CHANNEL=single
MITEL_LOG_LEVEL=info
MITEL_ALERTS_ENABLED=false
MITEL_ALERT_EMAIL=admin@example.com
```

### Table Mappings

In `config/mitel.php`, define mappings for each table:

```php
'table_mappings' => [
    'source_table_name' => [
        'source_table' => 'tblConfig_Agent',
        'target_table' => 'mitel_agents',
        'unique_key' => 'Pkey', // For upsert operations
        'timestamp_column' => 'ModifiedOn', // For incremental sync
        
        'column_map' => [
            'SourceColumn' => 'target_column',
        ],
        
        'filters' => [
            'IsActive' => true, // Only sync active records
        ],
        
        'transformations' => [
            'column_name' => fn($value) => transform($value),
        ],
    ],
],
```

## Available Tables in CCMData

Based on the DDL, here are some key tables you can sync:

### Configuration Tables
- `tblConfig_Agent` - Agent configurations
- `tblConfig_AgentGroup` - Agent group definitions
- `tblConfig_DNIS` - Phone number configurations
- `tblConfig_Exchange_Response` - Email response templates
- `tblConfig_Region` - Region configurations

### Real-time Tables
- `tblReal_Agent` - Real-time agent states
- `tblReal_AgentGroup` - Real-time group statistics

### Historical Tables
- `tblHist_Agent` - Historical agent performance
- `tblHist_AgentGroup` - Historical group performance

### Scheduling Tables
- `tblCCS_SA_ActualTimeEvent` - Actual time events
- `tblCCS_ShiftType` - Shift type definitions
- `tblCCS_BreakType` - Break type definitions

## Security Notes

1. **Password Encryption**: Passwords in `mitel_connections` are automatically encrypted using Laravel's `Crypt` facade
2. **Database Security**: Ensure your PostgreSQL database has proper access controls
3. **SQL Server Access**: Use read-only credentials for the Mitel database when possible
4. **Network Security**: Ensure proper firewall rules between CRM and Mitel database server (10.0.0.84:1433)

## Troubleshooting

### Connection Issues

Test the connection:
```bash
php artisan mitel:sync "Primary CCMData" --test
```

Common issues:
- SQL Server not accessible from CRM server
- Incorrect credentials
- SQL Server not configured for TCP/IP connections
- Firewall blocking port 1433

### Sync Failures

Check logs:
```bash
tail -f storage/logs/laravel.log
```

Check sync log table:
```php
MitelSyncLog::failed()->latest()->first()->error_message
```

### Performance Optimization

For large datasets:
1. Use incremental sync instead of full sync
2. Adjust chunk size in config
3. Sync during off-peak hours
4. Add indexes to timestamp columns used for incremental sync

## Extending the Service

### Add New Table Sync

1. Create target table migration
2. Add table mapping to `config/mitel.php`
3. Add table name to `sync_tables` array
4. Run sync

### Custom Transformations

Add transformers in the mapping configuration:

```php
'transformations' => [
    'phone' => fn($value) => preg_replace('/[^0-9]/', '', $value),
    'date' => fn($value) => $value ? Carbon::parse($value) : null,
],
```

### Sync Notifications

Enable alerts in `config/mitel.php`:

```php
'alerts' => [
    'enabled' => true,
    'email' => 'admin@example.com',
    'on_failure' => true,
    'on_success' => false,
],
```

## API Endpoints (To Be Implemented)

You can create API endpoints to:
- List connections
- Create/update/delete connections
- Trigger manual sync
- View sync logs
- View sync statistics

## Cron Schedule Examples

```env
# Every minute
MITEL_SYNC_SCHEDULE="* * * * *"

# Every 5 minutes
MITEL_SYNC_SCHEDULE="*/5 * * * *"

# Every 15 minutes
MITEL_SYNC_SCHEDULE="*/15 * * * *"

# Every 30 minutes
MITEL_SYNC_SCHEDULE="*/30 * * * *"

# Every hour
MITEL_SYNC_SCHEDULE="0 * * * *"

# Every 6 hours
MITEL_SYNC_SCHEDULE="0 */6 * * *"

# Daily at 2 AM
MITEL_SYNC_SCHEDULE="0 2 * * *"

# Weekdays only at 8 AM
MITEL_SYNC_SCHEDULE="0 8 * * 1-5"
```

## Support

For issues or questions, refer to:
- [crm-claude-summary/ddl.txt](../crm-claude-summary/ddl.txt) - Full CCMData database schema
- Laravel documentation for scheduling: https://laravel.com/docs/scheduling
- SQL Server PHP drivers: https://docs.microsoft.com/en-us/sql/connect/php/

## License

Internal use only for JKDM CRM project.
