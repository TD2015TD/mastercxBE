# Mitel Sync - Quick Reference

## Setup
```bash
# Run migrations
php artisan migrate

# Run setup script (interactive)
./setup-mitel-sync.sh

# Or create connection manually
php artisan tinker
> MitelConnection::create([...])
```

## Commands

### Test Connection
```bash
php artisan mitel:sync "Primary CCMData" --test
```

### Manual Sync
```bash
# Sync specific connection
php artisan mitel:sync "Primary CCMData"

# Sync all active connections
php artisan mitel:sync --all

# Incremental sync (only changed records)
php artisan mitel:sync "Primary CCMData" --type=incremental

# Full sync (all records)
php artisan mitel:sync "Primary CCMData" --type=full

# Sync specific tables only
php artisan mitel:sync "Primary CCMData" --tables=tblConfig_Agent
```

## Configuration Files

- `config/database.php` - Database connections (mitel_dynamic configured at runtime from DB)
- `config/mitel.php` - Sync tables (prioritizes tblData), schedules, and mappings
- `app/Console/Kernel.php` - Cron schedule registration
- `.env` - Environment variables (optional)

## Sync Priority

The sync is configured to prioritize **tblData tables** and their lookup/config dependencies:

1. **Lookup tables** - Core reference data (DayOfWeek, TimeInterval, etc.)
2. **Config tables** - Agent, Queue, DNIS configurations
3. **tblData tables** - Performance and trace data (AgentPerformance, QueuePerformance, InboundTrace, etc.)

All connections are **stored in the database** (not in config files) for dynamic management.

## Key Files

### Models
- `app/Models/MitelConnection.php` - Connection management
- `app/Models/MitelSyncLog.php` - Sync history

### Services
- `app/Services/MitelDataSyncService.php` - Sync logic

### Commands
- `app/Console/Commands/SyncMitelData.php` - Artisan command

### Migrations
- `database/migrations/*_create_mitel_connections_table.php`
- `database/migrations/*_create_mitel_sync_logs_table.php`
- `database/migrations/*_create_mitel_agents_table.php`

## Database Tables

### CRM Database (PostgreSQL)
- `mitel_connections` - Connection configurations
- `mitel_sync_logs` - Sync history and statistics
- `mitel_agents` - Example: synced agent data
- `mitel_agent_groups` - Example: synced group data
- Add more as needed...

### Source Database (SQL Server)
- Server: `10.0.0.84:1433`
- Database: `CCMData`
- Schema: See `crm-claude-summary/ddl.txt`

## Monitor Sync Status

### Check Logs
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Filter for Mitel sync
tail -f storage/logs/laravel.log | grep -i mitel
```

### Query Database
```sql
-- Check connection status
SELECT name, sync_status, last_sync_at, last_sync_message 
FROM mitel_connections;

-- View recent sync logs
SELECT * FROM mitel_sync_logs 
ORDER BY started_at DESC 
LIMIT 10;

-- Sync statistics
SELECT 
    status,
    COUNT(*) as count,
    SUM(records_fetched) as total_fetched,
    SUM(records_inserted) as total_inserted,
    AVG(EXTRACT(EPOCH FROM (completed_at - started_at))) as avg_duration
FROM mitel_sync_logs
GROUP BY status;
```

### Via Tinker
```bash
php artisan tinker
```

```php
// Get connection status
$conn = MitelConnection::first();
echo "Status: {$conn->sync_status}\n";
echo "Last Sync: {$conn->last_sync_at}\n";

// Recent logs
$logs = MitelSyncLog::latest()->take(5)->get();
foreach ($logs as $log) {
    echo "{$log->started_at}: {$log->status} - {$log->records_fetched} records\n";
}

// Test connection
use App\Services\MitelDataSyncService;
$service = new MitelDataSyncService($conn);
$result = $service->testConnection();
print_r($result);
```

## Cron Schedule

The sync runs automatically based on `config/mitel.php`:

```php
'sync_schedule' => env('MITEL_SYNC_SCHEDULE', '0 * * * *'),
```

### Common Schedules
```
* * * * *       Every minute
*/5 * * * *     Every 5 minutes
*/15 * * * *    Every 15 minutes
*/30 * * * *    Every 30 minutes
0 * * * *       Every hour
0 */6 * * *     Every 6 hours
0 2 * * *       Daily at 2 AM
0 8 * * 1-5     Weekdays at 8 AM
```

## Adding New Tables

1. **Create target table migration**
   ```bash
   php artisan make:migration create_mitel_[table_name]_table
   ```

2. **Define schema** matching your column mapping

3. **Add mapping to config/mitel.php**
   ```php
   'table_mappings' => [
       'tblSource_Table' => [
           'source_table' => 'tblSource_Table',
           'target_table' => 'mitel_target_table',
           'unique_key' => 'Pkey',
           'timestamp_column' => 'ModifiedOn',
           'column_map' => [
               'SourceCol' => 'target_col',
           ],
       ],
   ],
   ```

4. **Add to sync_tables array**
   ```php
   'sync_tables' => [
       'tblSource_Table',
   ],
   ```

5. **Run migration and sync**
   ```bash
   php artisan migrate
   php artisan mitel:sync "Primary CCMData"
   ```

## Troubleshooting

### Connection Failed
1. Check SQL Server is running and accessible
2. Verify credentials in mitel_connections table
3. Check firewall allows port 1433
4. Test with: `php artisan mitel:sync "Primary CCMData" --test`

### Sync Failures
1. Check logs: `tail -f storage/logs/laravel.log`
2. View error: `SELECT error_message FROM mitel_sync_logs ORDER BY started_at DESC LIMIT 1;`
3. Test connection first
4. Check table mappings in config/mitel.php

### Performance Issues
1. Use incremental sync: `--type=incremental`
2. Sync specific tables: `--tables=tblConfig_Agent`
3. Adjust schedule (less frequent)
4. Add indexes to timestamp columns
5. Increase chunk size in config

### Missing Data
1. Check filters in table mapping
2. Verify timestamp_column for incremental sync
3. Run full sync: `--type=full`
4. Check source table has data

## Security Checklist

- [ ] Passwords are encrypted (automatic in MitelConnection model)
- [ ] Use read-only SQL Server user when possible
- [ ] Firewall rules properly configured
- [ ] PostgreSQL access controls in place
- [ ] Credentials not in version control
- [ ] SSL/TLS encryption enabled for SQL Server connection

## Environment Variables

Optional configuration in `.env`:

```env
# Sync Schedule
MITEL_SYNC_SCHEDULE="0 * * * *"

# Sync Type
MITEL_SYNC_TYPE=incremental

# Logging
MITEL_LOG_CHANNEL=single
MITEL_LOG_LEVEL=info

# Alerts
MITEL_ALERTS_ENABLED=false
MITEL_ALERT_EMAIL=admin@example.com
```

## Support

- Full documentation: [MITEL_SYNC_GUIDE.md](MITEL_SYNC_GUIDE.md)
- DDL Reference: `crm-claude-summary/ddl.txt`
- Laravel Scheduling: https://laravel.com/docs/scheduling

---

Last Updated: January 12, 2026
