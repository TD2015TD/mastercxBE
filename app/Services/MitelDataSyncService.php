<?php

namespace App\Services;

use App\Models\MitelConnection;
use App\Models\MitelSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class MitelDataSyncService
{
    protected MitelConnection $connection;
    protected ?MitelSyncLog $syncLog = null;

    public function __construct(MitelConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Execute the data sync for specified tables.
     */
    public function sync(array $tables = [], string $syncType = 'full'): bool
    {
        if (!$this->connection->is_active) {
            Log::warning("Connection {$this->connection->name} is not active");
            return false;
        }

        try {
            // Update connection status
            $this->connection->updateSyncStatus('running', 'Sync started');

            // Create sync log
            $this->syncLog = MitelSyncLog::create([
                'mitel_connection_id' => $this->connection->id,
                'sync_type' => $syncType,
                'status' => 'started',
                'started_at' => now(),
            ]);

            // Get configured tables or use default
            $tablesToSync = !empty($tables) ? $tables : $this->getConfiguredTables();

            // Configure dynamic connection
            $this->configureDynamicConnection();

            $totalFetched = 0;
            $totalInserted = 0;
            $totalUpdated = 0;
            $totalFailed = 0;

            foreach ($tablesToSync as $table) {
                try {
                    Log::info("Syncing table: {$table}");
                    
                    $result = $this->syncTable($table, $syncType);
                    
                    $totalFetched += $result['fetched'];
                    $totalInserted += $result['inserted'];
                    $totalUpdated += $result['updated'];
                    $totalFailed += $result['failed'];

                } catch (\Exception $e) {
                    Log::error("Error syncing table {$table}: " . $e->getMessage());
                    $totalFailed++;
                }
            }

            // Update sync log
            $this->syncLog->update([
                'status' => 'success',
                'completed_at' => now(),
                'records_fetched' => $totalFetched,
                'records_inserted' => $totalInserted,
                'records_updated' => $totalUpdated,
                'records_failed' => $totalFailed,
            ]);

            $this->connection->updateSyncStatus(
                'success',
                "Synced {$totalFetched} records successfully"
            );

            return true;

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error("Mitel sync failed: {$errorMessage}");

            if ($this->syncLog) {
                $this->syncLog->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => $errorMessage,
                ]);
            }

            $this->connection->updateSyncStatus('failed', $errorMessage);

            return false;
        }
    }

    /**
     * Sync a single table from Mitel database.
     */
    protected function syncTable(string $tableName, string $syncType): array
    {
        $fetched = 0;
        $inserted = 0;
        $updated = 0;
        $failed = 0;

        // Get the mapping configuration for this table
        $mapping = $this->getTableMapping($tableName);
        
        if (!$mapping) {
            Log::warning("No mapping configuration found for table: {$tableName}");
            return compact('fetched', 'inserted', 'updated', 'failed');
        }

        Log::info("Syncing table: {$tableName}", ['mapping' => $mapping]);

        // Fetch data from Mitel database
        // Check if there's a custom query (for JOINs)
        if (isset($mapping['custom_query'])) {
            Log::info("Using custom query for {$tableName}");
            
            // Build parameters for custom query
            $params = [];
            if ($syncType === 'incremental' && isset($mapping['timestamp_column'])) {
                $lastSync = $this->connection->last_sync_at;
                if ($lastSync) {
                    $params[] = $lastSync->format('Y-m-d H:i:s');
                } else {
                    $params[] = '1900-01-01 00:00:00';
                }
            } else {
                // For full sync, use a very old date
                $params[] = '1900-01-01 00:00:00';
            }
            
            Log::info("Executing custom query with params", ['params' => $params]);
            $records = DB::connection('mitel_dynamic')
                ->select($mapping['custom_query'], $params);
            $fetched = count($records);
            Log::info("Custom query returned {$fetched} records");
        } else {
            // Standard table query
            $query = DB::connection('mitel_dynamic')
                ->table($mapping['source_table']);

            // Add incremental sync logic if needed
            if ($syncType === 'incremental' && isset($mapping['timestamp_column'])) {
                $lastSync = $this->connection->last_sync_at;
                if ($lastSync) {
                    $query->where($mapping['timestamp_column'], '>', $lastSync);
                }
            }

            // Add any filters from configuration
            if (isset($mapping['filters'])) {
                foreach ($mapping['filters'] as $column => $value) {
                    $query->where($column, $value);
                }
            }

            Log::info("Executing query for {$tableName}");
            $records = $query->get();
            $fetched = $records->count();
        }
        
        Log::info("Fetched {$fetched} records from {$tableName}");

        // Log first record columns for AgentPerformanceByPeriodStats
        if ($fetched > 0 && $tableName === 'AgentPerformanceByPeriodStats') {
            $firstRecord = (array) $records[0];
            Log::error("DEBUG: AgentPerformanceByPeriodStats source columns:", [
                'columns' => array_keys($firstRecord),
                'IntervalStartTime' => $firstRecord['IntervalStartTime'] ?? 'MISSING',
                'HourMinute' => $firstRecord['HourMinute'] ?? 'MISSING',
            ]);
        }

        // Process each record
        $recordCount = 0;
        foreach ($records as $record) {
            try {
                $data = $this->transformRecord($record, $mapping);
                
                // Log first record for AgentPerformanceByPeriodStats to debug
                if ($recordCount === 0 && $tableName === 'AgentPerformanceByPeriodStats') {
                    Log::error("DEBUG: First AgentPerformanceByPeriodStats record transformed:", [
                        'agent_id' => $data['agent_id'] ?? 'MISSING',
                        'interval_start_time' => $data['interval_start_time'] ?? 'MISSING',
                        'hour_minute' => $data['hour_minute'] ?? 'MISSING',
                    ]);
                }
                $recordCount++;
                
                // Get the target unique key(s) (after column mapping transformation)
                $uniqueKeys = [];
                if (isset($mapping['unique_key'])) {
                    $sourceKeys = is_array($mapping['unique_key']) ? $mapping['unique_key'] : [$mapping['unique_key']];
                    
                    foreach ($sourceKeys as $sourceKey) {
                        // If unique_key is a source column, find its target column name
                        if (isset($mapping['column_map'][$sourceKey])) {
                            $uniqueKeys[] = $mapping['column_map'][$sourceKey];
                        } else {
                            $uniqueKeys[] = $sourceKey;
                        }
                    }
                }
                
                // Insert or update in local database
                if (!empty($uniqueKeys)) {
                    // Check if all unique key values exist in data
                    $hasAllKeys = true;
                    foreach ($uniqueKeys as $key) {
                        if (!isset($data[$key])) {
                            $hasAllKeys = false;
                            break;
                        }
                    }
                    
                    if ($hasAllKeys) {
                        try {
                            // Build where clause for composite or single keys
                            $query = DB::table($mapping['target_table']);
                            foreach ($uniqueKeys as $key) {
                                $query->where($key, $data[$key]);
                            }
                            $existing = $query->first();

                            if ($existing) {
                                $updateQuery = DB::table($mapping['target_table']);
                                foreach ($uniqueKeys as $key) {
                                    $updateQuery->where($key, $data[$key]);
                                }
                                $updateQuery->update($data);
                                $updated++;
                            } else {
                                DB::table($mapping['target_table'])->insert($data);
                                $inserted++;
                            }
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Log the actual error to understand what's happening
                            $errorMsg = $e->getMessage();
                            Log::error("Query error for table {$mapping['target_table']}: " . $errorMsg);
                            $failed++;
                            continue;
                        }
                    } else {
                        Log::warning("Missing unique key values for table {$mapping['target_table']}");
                        $failed++;
                        continue;
                    }
                } else {
                    try {
                        DB::table($mapping['target_table'])->insert($data);
                        $inserted++;
                    } catch (\Illuminate\Database\QueryException $e) {
                        Log::error("Insert error for table {$mapping['target_table']}: " . $e->getMessage());
                        $failed++;
                        continue;
                    }
                }

            } catch (\Exception $e) {
                Log::error("Failed to process record: " . $e->getMessage());
                $failed++;
            }
        }

        return compact('fetched', 'inserted', 'updated', 'failed');
    }

    /**
     * Configure dynamic database connection.
     */
    protected function configureDynamicConnection(): void
    {
        Config::set(
            'database.connections.mitel_dynamic',
            $this->connection->getConnectionConfig()
        );

        // Test the connection
        DB::connection('mitel_dynamic')->getPdo();
    }

    /**
     * Get configured tables to sync.
     */
    protected function getConfiguredTables(): array
    {
        return config('mitel.sync_tables', []);
    }

    /**
     * Get table mapping configuration.
     */
    protected function getTableMapping(string $tableName): ?array
    {
        $mappings = config('mitel.table_mappings', []);
        return $mappings[$tableName] ?? null;
    }

    /**
     * Transform record according to mapping rules.
     */
    protected function transformRecord($record, array $mapping): array
    {
        $data = [];
        
        // Convert record to array for easier handling
        $recordArray = (array) $record;
        
        // Debug: Log first record columns for AgentPerformanceByPeriodStats
        static $logged = false;
        if (!$logged && isset($mapping['source_table']) && $mapping['source_table'] === 'AgentPerformanceByPeriodStats') {
            Log::debug('AgentPerformanceByPeriodStats columns available:', array_keys($recordArray));
            if (isset($recordArray['IntervalStartTime'])) {
                Log::debug('IntervalStartTime value:', ['value' => $recordArray['IntervalStartTime'], 'type' => gettype($recordArray['IntervalStartTime'])]);
            }
            if (isset($recordArray['HourMinute'])) {
                Log::debug('HourMinute value:', ['value' => $recordArray['HourMinute'], 'type' => gettype($recordArray['HourMinute'])]);
            }
            $logged = true;
        }
        
        // Apply column mappings
        if (isset($mapping['column_map'])) {
            foreach ($mapping['column_map'] as $source => $target) {
                // Try exact match first (case-sensitive)
                if (array_key_exists($source, $recordArray)) {
                    $data[$target] = $recordArray[$source];
                } else {
                    // Fall back to case-insensitive search only if exact match not found
                    $found = false;
                    foreach ($recordArray as $key => $value) {
                        if (strcasecmp($key, $source) === 0) {
                            $data[$target] = $value;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $data[$target] = null;
                    }
                }
            }
        } else {
            // Direct mapping if no column map specified
            $data = $recordArray;
        }

        // Apply transformations
        if (isset($mapping['transformations'])) {
            foreach ($mapping['transformations'] as $column => $transformer) {
                if (is_callable($transformer) && isset($data[$column])) {
                    $data[$column] = $transformer($data[$column]);
                }
            }
        }

        // Add timestamps
        $data['synced_at'] = now();
        $data['updated_at'] = now();

        return $data;
    }

    /**
     * Test the connection.
     */
    public function testConnection(): array
    {
        try {
            $this->configureDynamicConnection();
            
            $result = DB::connection('mitel_dynamic')
                ->select('SELECT @@VERSION as version');

            return [
                'success' => true,
                'message' => 'Connection successful',
                'version' => $result[0]->version ?? 'Unknown',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
