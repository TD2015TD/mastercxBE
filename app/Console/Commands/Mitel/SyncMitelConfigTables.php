<?php

namespace App\Console\Commands\Mitel;

use App\Models\MitelConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class SyncMitelConfigTables extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mitel:sync-config
                          {connection? : The name of the Mitel connection to sync}
                          {--tables=* : Specific tables to sync (anis, dnis, phone_groups, agents)}
                          {--type=full : Sync type: full or incremental}
                          {--all : Sync all active connections}';

    /**
     * The console command description.
     */
    protected $description = 'Sync Mitel configuration tables (ANI, DNIS, Phone Number Groups, Agents) from SQL Server to PostgreSQL';
    
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Mitel configuration synchronization...');
        
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
        $this->info("Config sync completed: {$successCount} successful, {$failCount} failed");

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
                    $expandedTables = array_merge($expandedTables, array_map('trim', explode(',', $table)));
                } else {
                    $expandedTables[] = $table;
                }
            }
            $tables = $expandedTables;
        }

        // Default: sync all config tables
        if (empty($tables)) {
            $tables = ['anis', 'dnis', 'phone_groups', 'agents'];
        }

        $this->line("Connection: {$connection->name}");
        $this->line("Database: {$connection->database}@{$connection->host}");
        $this->line("Sync Type: {$syncType}");
        $this->line("Tables: " . implode(', ', $tables));
        $this->newLine();

        $fullSync = ($syncType === 'full');
        
        try {
            // Configure database connection dynamically using connection's built-in method
            config([
                'database.connections.sqlsrv_mitel_dynamic' => $connection->getConnectionConfig()
            ]);

            // Test the connection
            DB::connection('sqlsrv_mitel_dynamic')->getPdo();

            if (in_array('anis', $tables)) {
                $this->syncANIs($fullSync);
            }
            
            if (in_array('dnis', $tables)) {
                $this->syncDNIS($fullSync);
            }
            
            if (in_array('phone_groups', $tables)) {
                $this->syncPhoneNumberGroups($fullSync);
            }
            
            if (in_array('agents', $tables)) {
                $this->syncAgents($fullSync);
            }
            
            $this->newLine();
            $this->info('✓ Configuration sync completed successfully!');
            return 0;
            
        } catch (Exception $e) {
            $this->error('✗ Sync failed: ' . $e->getMessage());
            
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            
            return 1;
        }
    }
    
    private function syncANIs($fullSync = false)
    {
        $this->info('Syncing ANIs...');
        
        if ($fullSync) {
            DB::table('mitel_config_anis')->truncate();
            $this->warn('  - Truncated existing ANI records');
        }
        
        $anis = DB::connection('sqlsrv_mitel_dynamic')
            ->table('tblConfig_ANI')
            ->select([
                'Pkey as pkey',
                'Name as name',
                'ANI as ani',
                'DigitsToFollow as digits_to_follow',
                'Auto_Programmed as auto_programmed',
                'Last_Modified as last_modified',
                'FKSite as fk_site',
                'CreatedOn as created_on',
                'CreatedBy as created_by',
                'ModifiedOn as modified_on',
                'ModifiedBy as modified_by',
                'CreatedByClientId as created_by_client_id',
                'ModifiedByClientId as modified_by_client_id',
            ])
            ->get();
        
        $bar = $this->output->createProgressBar($anis->count());
        $bar->start();
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($anis as $ani) {
            $data = [
                'pkey' => $ani->pkey,
                'name' => $ani->name,
                'ani' => $ani->ani,
                'digits_to_follow' => $ani->digits_to_follow ?? 0,
                'auto_programmed' => $ani->auto_programmed ?? false,
                'last_modified' => $ani->last_modified,
                'fk_site' => $ani->fk_site,
                'created_on' => $ani->created_on,
                'created_by' => $ani->created_by,
                'modified_on' => $ani->modified_on,
                'modified_by' => $ani->modified_by,
                'created_by_client_id' => $ani->created_by_client_id,
                'modified_by_client_id' => $ani->modified_by_client_id,
                'synced_at' => now(),
                'updated_at' => now(),
            ];
            
            $exists = DB::table('mitel_config_anis')->where('pkey', $ani->pkey)->exists();
            
            if ($exists) {
                DB::table('mitel_config_anis')->where('pkey', $ani->pkey)->update($data);
                $updated++;
            } else {
                DB::table('mitel_config_anis')->insert($data);
                $inserted++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("  ✓ ANIs synced: {$inserted} inserted, {$updated} updated");
    }
    
    private function syncDNIS($fullSync = false)
    {
        $this->info('Syncing DNIS...');
        
        if ($fullSync) {
            DB::table('mitel_config_dnis')->truncate();
            $this->warn('  - Truncated existing DNIS records');
        }
        
        $dnisList = DB::connection('sqlsrv_mitel_dynamic')
            ->table('tblConfig_DNIS')
            ->select([
                'Pkey as pkey',
                'Name as name',
                'PhoneNumber as phone_number',
                'Reporting as reporting',
                'Service_Level as service_level',
                'Short_Abd as short_abd',
                'Auto_Programmed as auto_programmed',
                'UseAsDefault as use_as_default',
                'Last_Modified as last_modified',
                'FKNode as fk_node',
                'CreatedOn as created_on',
                'CreatedBy as created_by',
                'ModifiedOn as modified_on',
                'ModifiedBy as modified_by',
                'CreatedByClientId as created_by_client_id',
                'ModifiedByClientId as modified_by_client_id',
            ])
            ->get();
        
        $bar = $this->output->createProgressBar($dnisList->count());
        $bar->start();
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($dnisList as $dnis) {
            $data = [
                'pkey' => $dnis->pkey,
                'name' => $dnis->name,
                'phone_number' => $dnis->phone_number ?? '',
                'reporting' => $dnis->reporting,
                'service_level' => $dnis->service_level ?? 0,
                'short_abd' => $dnis->short_abd ?? 0,
                'auto_programmed' => $dnis->auto_programmed ?? false,
                'use_as_default' => $dnis->use_as_default ?? false,
                'last_modified' => $dnis->last_modified,
                'fk_node' => $dnis->fk_node,
                'created_on' => $dnis->created_on,
                'created_by' => $dnis->created_by,
                'modified_on' => $dnis->modified_on,
                'modified_by' => $dnis->modified_by,
                'created_by_client_id' => $dnis->created_by_client_id,
                'modified_by_client_id' => $dnis->modified_by_client_id,
                'synced_at' => now(),
                'updated_at' => now(),
            ];
            
            $exists = DB::table('mitel_config_dnis')->where('pkey', $dnis->pkey)->exists();
            
            if ($exists) {
                DB::table('mitel_config_dnis')->where('pkey', $dnis->pkey)->update($data);
                $updated++;
            } else {
                DB::table('mitel_config_dnis')->insert($data);
                $inserted++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("  ✓ DNIS synced: {$inserted} inserted, {$updated} updated");
    }
    
    private function syncPhoneNumberGroups($fullSync = false)
    {
        $this->info('Syncing Phone Number Groups...');
        
        if ($fullSync) {
            DB::table('mitel_config_phone_number_groups')->truncate();
            $this->warn('  - Truncated existing Phone Number Group records');
        }
        
        $groups = DB::connection('sqlsrv_mitel_dynamic')
            ->table('tblConfig_CA_PhoneNumberGroup')
            ->select([
                'Pkey as pkey',
                'Reporting as reporting',
                'Name as name',
                'CreatedOn as created_on',
                'CreatedBy as created_by',
                'ModifiedOn as modified_on',
                'ModifiedBy as modified_by',
                'CreatedByClientId as created_by_client_id',
                'ModifiedByClientId as modified_by_client_id',
            ])
            ->get();
        
        $bar = $this->output->createProgressBar($groups->count());
        $bar->start();
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($groups as $group) {
            $data = [
                'pkey' => $group->pkey,
                'reporting' => $group->reporting,
                'name' => $group->name,
                'created_on' => $group->created_on,
                'created_by' => $group->created_by,
                'modified_on' => $group->modified_on,
                'modified_by' => $group->modified_by,
                'created_by_client_id' => $group->created_by_client_id,
                'modified_by_client_id' => $group->modified_by_client_id,
                'synced_at' => now(),
                'updated_at' => now(),
            ];
            
            $exists = DB::table('mitel_config_phone_number_groups')->where('pkey', $group->pkey)->exists();
            
            if ($exists) {
                DB::table('mitel_config_phone_number_groups')->where('pkey', $group->pkey)->update($data);
                $updated++;
            } else {
                DB::table('mitel_config_phone_number_groups')->insert($data);
                $inserted++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Phone Number Groups synced: {$inserted} inserted, {$updated} updated");
    }
    
    private function syncAgents($fullSync = false)
    {
        $this->info('Syncing Agents...');
        
        if ($fullSync) {
            DB::table('mitel_config_agents')->truncate();
            $this->warn('  - Truncated existing Agent records');
        }
        
        $agents = DB::connection('sqlsrv_mitel_dynamic')
            ->table('tblConfig_Agent')
            ->select([
                'Pkey as pkey',
                'FKEmployee as fk_employee',
                'FKMasterAgent as fk_master_agent',
                'FirstName as first_name',
                'LastName as last_name',
                'Reporting as reporting',
                'Auto_Programmed as auto_programmed',
                'isActive as is_active',
                'ExistsOnPBX as exists_on_pbx',
                'Last_Modified as last_modified',
                'FKSite as fk_site',
                'FKNode as fk_node',
                'FKFailOver as fk_fail_over',
                'CreatedOn as created_on',
                'CreatedBy as created_by',
                'ModifiedOn as modified_on',
                'ModifiedBy as modified_by',
                'CreatedByClientId as created_by_client_id',
                'ModifiedByClientId as modified_by_client_id',
            ])
            ->get();
        
        $bar = $this->output->createProgressBar($agents->count());
        $bar->start();
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($agents as $agent) {
            $data = [
                'pkey' => $agent->pkey,
                'fk_employee' => $agent->fk_employee,
                'fk_master_agent' => $agent->fk_master_agent,
                'first_name' => $agent->first_name ?? '',
                'last_name' => $agent->last_name ?? '',
                'reporting' => $agent->reporting,
                'auto_programmed' => $agent->auto_programmed ?? false,
                'is_active' => $agent->is_active ?? true,
                'exists_on_pbx' => $agent->exists_on_pbx ?? true,
                'last_modified' => $agent->last_modified,
                'fk_site' => $agent->fk_site,
                'fk_node' => $agent->fk_node,
                'fk_fail_over' => $agent->fk_fail_over,
                'created_on' => $agent->created_on,
                'created_by' => $agent->created_by,
                'modified_on' => $agent->modified_on,
                'modified_by' => $agent->modified_by,
                'created_by_client_id' => $agent->created_by_client_id,
                'modified_by_client_id' => $agent->modified_by_client_id,
                'synced_at' => now(),
                'updated_at' => now(),
            ];
            
            $exists = DB::table('mitel_config_agents')->where('pkey', $agent->pkey)->exists();
            
            if ($exists) {
                DB::table('mitel_config_agents')->where('pkey', $agent->pkey)->update($data);
                $updated++;
            } else {
                DB::table('mitel_config_agents')->insert($data);
                $inserted++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Agents synced: {$inserted} inserted, {$updated} updated");
    }
}
