<?php

namespace App\Console\Commands;

use App\Models\MitelConnection;
use App\Services\MitelDataSyncService;
use Illuminate\Console\Command;

class SyncMitelData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mitel:sync
                          {connection? : The name of the Mitel connection to sync}
                          {--tables=* : Specific tables to sync}
                          {--type=full : Sync type: full or incremental}
                          {--test : Test connection only}
                          {--all : Sync all active connections}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data from Mitel CCMData database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Mitel data synchronization...');
        
        // Test connection only
        if ($this->option('test')) {
            return $this->testConnection();
        }

        // Sync all active connections
        if ($this->option('all')) {
            return $this->syncAllConnections();
        }

        // Sync specific connection
        $connectionName = $this->argument('connection');
        
        if (!$connectionName) {
            // Show available connections
            $connections = MitelConnection::active()->get();
            
            if ($connections->isEmpty()) {
                $this->error('No active Mitel connections found.');
                $this->info('Please create a connection first using the admin interface or database.');
                return 1;
            }

            $connectionName = $this->choice(
                'Select a connection to sync',
                $connections->pluck('name')->toArray()
            );
        }

        $connection = MitelConnection::where('name', $connectionName)
            ->active()
            ->first();

        if (!$connection) {
            $this->error("Connection '{$connectionName}' not found or is not active.");
            return 1;
        }

        return $this->syncConnection($connection);
    }

    /**
     * Test connection.
     */
    protected function testConnection(): int
    {
        $connectionName = $this->argument('connection');
        
        if (!$connectionName) {
            $this->error('Please specify a connection name for testing.');
            return 1;
        }

        $connection = MitelConnection::where('name', $connectionName)->first();

        if (!$connection) {
            $this->error("Connection '{$connectionName}' not found.");
            return 1;
        }

        $this->info("Testing connection to '{$connection->name}'...");
        
        $syncService = new MitelDataSyncService($connection);
        $result = $syncService->testConnection();

        if ($result['success']) {
            $this->info('✓ Connection successful!');
            $this->line("SQL Server Version: {$result['version']}");
            return 0;
        } else {
            $this->error('✗ Connection failed!');
            $this->error($result['message']);
            return 1;
        }
    }

    /**
     * Sync all active connections.
     */
    protected function syncAllConnections(): int
    {
        $connections = MitelConnection::active()->get();

        if ($connections->isEmpty()) {
            $this->error('No active Mitel connections found.');
            return 1;
        }

        $this->info("Found {$connections->count()} active connection(s) to sync.");

        $successCount = 0;
        $failCount = 0;

        foreach ($connections as $connection) {
            $this->newLine();
            $this->info("Syncing connection: {$connection->name}");
            
            $result = $this->syncConnection($connection);
            
            if ($result === 0) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Sync completed: {$successCount} successful, {$failCount} failed");

        return $failCount > 0 ? 1 : 0;
    }

    /**
     * Sync a specific connection.
     */
    protected function syncConnection(MitelConnection $connection): int
    {
        $tables = $this->option('tables');
        $syncType = $this->option('type');

        // Handle comma-separated tables
        if (!empty($tables)) {
            $expandedTables = [];
            foreach ($tables as $table) {
                if (str_contains($table, ',')) {
                    // Split comma-separated tables
                    $expandedTables = array_merge($expandedTables, array_map('trim', explode(',', $table)));
                } else {
                    $expandedTables[] = $table;
                }
            }
            $tables = $expandedTables;
        }

        $this->line("Connection: {$connection->name}");
        $this->line("Database: {$connection->database}@{$connection->host}");
        $this->line("Sync Type: {$syncType}");
        
        if (!empty($tables)) {
            $this->line("Tables: " . implode(', ', $tables));
        }

        $this->newLine();

        try {
            $syncService = new MitelDataSyncService($connection);
            
            $progressBar = $this->output->createProgressBar();
            $progressBar->setFormat('verbose');
            $progressBar->start();

            $success = $syncService->sync($tables, $syncType);

            $progressBar->finish();
            $this->newLine(2);

            if ($success) {
                $latestLog = $connection->latestSyncLog;
                
                $this->info('✓ Sync completed successfully!');
                
                if ($latestLog) {
                    $this->table(
                        ['Metric', 'Count'],
                        [
                            ['Records Fetched', $latestLog->records_fetched],
                            ['Records Inserted', $latestLog->records_inserted],
                            ['Records Updated', $latestLog->records_updated],
                            ['Records Failed', $latestLog->records_failed],
                            ['Duration', $latestLog->duration . ' seconds'],
                        ]
                    );
                }

                return 0;
            } else {
                $this->error('✗ Sync failed!');
                $this->error($connection->last_sync_message ?? 'Unknown error');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('✗ Sync failed with exception!');
            $this->error($e->getMessage());
            
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }
}
