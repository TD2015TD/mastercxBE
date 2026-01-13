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

        // Fetch data from Mitel database
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

        $records = $query->get();
        $fetched = $records->count();

        Log::info("Fetched {$fetched} records from {$tableName}");

        // Process each record
        foreach ($records as $record) {
            try {
                $data = $this->transformRecord($record, $mapping);
                
                // Get the target unique key (after column mapping transformation)
                $uniqueKey = null;
                if (isset($mapping['unique_key'])) {
                    // If unique_key is a source column, find its target column name
                    if (isset($mapping['column_map'][$mapping['unique_key']])) {
                        $uniqueKey = $mapping['column_map'][$mapping['unique_key']];
                    } else {
                        $uniqueKey = $mapping['unique_key'];
                    }
                }
                
                // Insert or update in local database
                if ($uniqueKey && isset($data[$uniqueKey])) {
                    try {
                        $existing = DB::table($mapping['target_table'])
                            ->where($uniqueKey, $data[$uniqueKey])
                            ->first();

                        if ($existing) {
                            DB::table($mapping['target_table'])
                                ->where($uniqueKey, $data[$uniqueKey])
                                ->update($data);
                            $updated++;
                        } else {
                            DB::table($mapping['target_table'])->insert($data);
                            $inserted++;
                        }
                    } catch (\Illuminate\Database\QueryException $e) {
                        // If table doesn't exist, log warning and skip
                        if (str_contains($e->getMessage(), 'does not exist') || str_contains($e->getMessage(), 'relation') && str_contains($e->getMessage(), 'does not exist')) {
                            Log::warning("Table {$mapping['target_table']} does not exist. Please create migrations for Mitel data tables.");
                            $failed++;
                            continue;
                        }
                        
                        // For other query errors (like duplicate keys), try to log details
                        Log::error("Query error for table {$mapping['target_table']}: " . $e->getMessage());
                        throw $e;
                    }
                } else {
                    try {
                        DB::table($mapping['target_table'])->insert($data);
                        $inserted++;
                    } catch (\Illuminate\Database\QueryException $e) {
                        if (str_contains($e->getMessage(), 'does not exist') || str_contains($e->getMessage(), 'relation') && str_contains($e->getMessage(), 'does not exist')) {
                            Log::warning("Table {$mapping['target_table']} does not exist. Please create migrations for Mitel data tables.");
                            $failed++;
                            continue;
                        }
                        Log::error("Insert error for table {$mapping['target_table']}: " . $e->getMessage());
                        throw $e;
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
        
        // Create case-insensitive lookup
        $recordLookup = array_change_key_case($recordArray, CASE_LOWER);
        
        // Apply column mappings
        if (isset($mapping['column_map'])) {
            foreach ($mapping['column_map'] as $source => $target) {
                // Try case-insensitive lookup
                $sourceKey = strtolower($source);
                
                if (isset($recordLookup[$sourceKey])) {
                    $data[$target] = $recordLookup[$sourceKey];
                } elseif (isset($recordArray[$source])) {
                    $data[$target] = $recordArray[$source];
                } else {
                    $data[$target] = null;
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
