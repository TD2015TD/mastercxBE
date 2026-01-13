<?php

namespace App\Console\Commands;

use App\Models\MitelConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class ListMitelTables extends Command
{
    protected $signature = 'mitel:list-tables {connection_id?}';
    protected $description = 'List all tables in the Mitel SQL Server database';

    public function handle()
    {
        $connectionId = $this->argument('connection_id');
        
        if (!$connectionId) {
            $connections = MitelConnection::where('is_active', true)->get();
            
            if ($connections->isEmpty()) {
                $this->error('No active Mitel connections found.');
                return 1;
            }
            
            if ($connections->count() === 1) {
                $connection = $connections->first();
            } else {
                $choices = $connections->pluck('name', 'id')->toArray();
                $connectionId = $this->choice('Select a connection', $choices);
                $connection = $connections->firstWhere('id', $connectionId);
            }
        } else {
            $connection = MitelConnection::find($connectionId);
        }
        
        if (!$connection) {
            $this->error('Connection not found.');
            return 1;
        }
        
        $this->info("Listing tables from: {$connection->name}");
        $this->info("Database: {$connection->database}@{$connection->host}");
        
        try {
            // Configure dynamic connection
            Config::set('database.connections.mitel_dynamic', $connection->getConnectionConfig());
            
            // Query SQL Server system tables to list all user tables
            $tables = DB::connection('mitel_dynamic')
                ->select("SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
            
            $this->newLine();
            $this->info("Found " . count($tables) . " tables:");
            $this->newLine();
            
            // Group by prefix
            $grouped = [];
            foreach ($tables as $table) {
                $fullName = $table->TABLE_SCHEMA . '.' . $table->TABLE_NAME;
                $grouped[$fullName] = $table->TABLE_NAME;
            }
            
            // Display tables
            $this->table(['Table Name'], array_map(fn($t) => [$t], array_values($grouped)));
            
            // Show tables matching config patterns
            $configTables = config('mitel.sync_tables', []);
            $this->newLine();
            $this->info("Tables configured in config/mitel.php: " . count($configTables));
            
            $missing = [];
            foreach ($configTables as $configTable) {
                if (!in_array($configTable, array_values($grouped))) {
                    $missing[] = $configTable;
                }
            }
            
            if (!empty($missing)) {
                $this->newLine();
                $this->warn("Tables in config but NOT found in SQL Server:");
                foreach ($missing as $missingTable) {
                    $this->line("  ❌ {$missingTable}");
                }
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Failed to list tables: " . $e->getMessage());
            return 1;
        }
    }
}
