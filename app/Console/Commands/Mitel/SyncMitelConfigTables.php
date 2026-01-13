<?php

namespace App\Console\Commands\Mitel;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class SyncMitelConfigTables extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mitel:sync-config
                          {--table=* : Specific tables to sync (anis, dnis, phone_groups)}
                          {--full : Full sync (delete and reimport all records)}';

    /**
     * The console command description.
     */
    protected $description = 'Sync Mitel configuration tables (ANI, DNIS, Phone Number Groups) from SQL Server to PostgreSQL';

    private $sqlServerConnection = 'sqlsrv_mitel';
    
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Mitel Configuration Sync...');
        
        $tables = $this->option('table');
        $fullSync = $this->option('full');
        
        // Default: sync all tables
        if (empty($tables)) {
            $tables = ['anis', 'dnis', 'phone_groups'];
        }
        
        try {
            if (in_array('anis', $tables)) {
                $this->syncANIs($fullSync);
            }
            
            if (in_array('dnis', $tables)) {
                $this->syncDNIS($fullSync);
            }
            
            if (in_array('phone_groups', $tables)) {
                $this->syncPhoneNumberGroups($fullSync);
            }
            
            $this->info('✓ Mitel Configuration Sync completed successfully!');
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('✗ Sync failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
    
    private function syncANIs($fullSync = false)
    {
        $this->info('Syncing ANIs...');
        
        if ($fullSync) {
            DB::table('mitel_config_anis')->truncate();
            $this->warn('  - Truncated existing ANI records');
        }
        
        $anis = DB::connection($this->sqlServerConnection)
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
        
        $dnisList = DB::connection($this->sqlServerConnection)
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
        
        $groups = DB::connection($this->sqlServerConnection)
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
}
