<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate from old mitel_config_agents schema to new schema
     * Old: agent_id, name, reporting_id, dialable
     * New: pkey, fk_master_agent, fk_employee, first_name, last_name, reporting
     */
    public function up(): void
    {
        // Check if we have the old schema (agent_id primary key instead of pkey)
        $hasOldSchema = Schema::hasColumn('mitel_config_agents', 'agent_id') &&
                        !Schema::hasColumn('mitel_config_agents', 'pkey');

        if ($hasOldSchema) {
            echo "Migrating mitel_config_agents from old schema to new schema...\n";

            // Step 1: Drop all foreign key constraints referencing agent_id
            $this->dropForeignKeys();

            // Step 2: Backup and recreate table with new schema
            Schema::rename('mitel_config_agents', 'mitel_config_agents_old');

            // Create new table with new schema
            Schema::create('mitel_config_agents', function (Blueprint $table) {
                $table->uuid('pkey')->primary();
                $table->uuid('fk_employee')->nullable(); // Link to CRM agents.employee_id
                $table->uuid('fk_master_agent')->nullable(); // Original agent UUID from Mitel
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('reporting', 50)->nullable();
                $table->boolean('auto_programmed')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('exists_on_pbx')->default(true);
                $table->timestamp('last_modified')->nullable();
                $table->uuid('fk_site')->nullable();
                $table->uuid('fk_node')->nullable();
                $table->uuid('fk_fail_over')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->string('created_by', 255)->nullable();
                $table->timestamp('modified_on')->nullable();
                $table->string('modified_by', 255)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('synced_at')->nullable();
                
                $table->index('reporting');
                $table->index('fk_employee');
                $table->index('fk_master_agent');
            });

            // Step 3: Migrate data from old table to new table
            DB::statement("
                INSERT INTO mitel_config_agents (
                    pkey,
                    fk_master_agent,
                    first_name,
                    last_name,
                    reporting,
                    is_active,
                    created_at,
                    updated_at,
                    synced_at
                )
                SELECT
                    CASE 
                        WHEN agent_id ~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' 
                        THEN agent_id::uuid
                        ELSE gen_random_uuid()
                    END as pkey,
                    CASE 
                        WHEN agent_id ~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' 
                        THEN agent_id::uuid
                        ELSE NULL
                    END as fk_master_agent,
                    COALESCE(SPLIT_PART(name, ' ', 1), '') as first_name,
                    CASE 
                        WHEN ARRAY_LENGTH(STRING_TO_ARRAY(COALESCE(name, ''), ' '), 1) > 1 
                        THEN SUBSTRING(name FROM POSITION(' ' IN name) + 1)
                        ELSE ''
                    END as last_name,
                    COALESCE(reporting_id, dialable) as reporting,
                    true as is_active,
                    created_at,
                    updated_at,
                    synced_at
                FROM mitel_config_agents_old
            ");

            // Step 4: Update foreign key columns in dependent tables  
            // Note: These tables reference agent_id, which should now reference pkey
            // But data migration already happened via sync, so we just need to allow nulls temporarily
            
            // Drop old table
            Schema::dropIfExists('mitel_config_agents_old');

            echo "Migration complete. Note: Foreign keys to mitel_config_agents need to be manually recreated if needed.\n";
            echo "The Mitel sync process will re-populate data on next sync.\n";
            
        } else if (!Schema::hasColumn('mitel_config_agents', 'fk_master_agent')) {
            // Table has pkey but missing fk_master_agent - just add missing columns
            echo "Adding missing columns to mitel_config_agents...\n";
            Schema::table('mitel_config_agents', function (Blueprint $table) {
                if (!Schema::hasColumn('mitel_config_agents', 'fk_master_agent')) {
                    $table->uuid('fk_master_agent')->nullable()->after('fk_employee');
                    $table->index('fk_master_agent');
                }
                if (!Schema::hasColumn('mitel_config_agents', 'reporting')) {
                    $table->string('reporting', 50)->nullable()->after('last_name');
                }
                if (!Schema::hasColumn('mitel_config_agents', 'first_name')) {
                    $table->string('first_name', 100)->nullable();
                }
                if (!Schema::hasColumn('mitel_config_agents', 'last_name')) {
                    $table->string('last_name', 100)->nullable();
                }
            });
        } else {
            echo "mitel_config_agents table already has the new schema. No migration needed.\n";
        }
    }

    /**
     * Drop foreign keys referencing agent_id on mitel_config_agents
     */
    private function dropForeignKeys(): void
    {
        $tables = [
            'mitel_data_agent_performance',
            'mitel_data_inbound_trace',
            'mitel_data_outbound_trace',
            'mitel_data_agent_trace_events',
            'mitel_data_agent_events',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Get foreign key names
            $foreignKeys = DB::select("
                SELECT constraint_name
                FROM information_schema.table_constraints
                WHERE table_name = ? 
                AND constraint_type = 'FOREIGN KEY'
                AND constraint_name LIKE '%agent_id%'
            ", [$tableName]);

            foreach ($foreignKeys as $fk) {
                try {
                    DB::statement("ALTER TABLE {$tableName} DROP CONSTRAINT IF EXISTS {$fk->constraint_name}");
                    echo "Dropped foreign key {$fk->constraint_name} from {$tableName}\n";
                } catch (\Exception $e) {
                    echo "Could not drop {$fk->constraint_name}: {$e->getMessage()}\n";
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot safely reverse this migration without data loss
        echo "This migration cannot be safely reversed.\n";
        echo "If you need to rollback, restore from database backup.\n";
    }
};
