<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agent Configuration
        if (!Schema::hasTable('mitel_config_agents')) {
            Schema::create('mitel_config_agents', function (Blueprint $table) {
                $table->uuid('pkey')->primary();
                $table->uuid('fk_employee')->nullable(); // Link to CRM agents.employee_id
                $table->uuid('fk_master_agent')->nullable();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('reporting', 50)->nullable();
                $table->boolean('auto_programmed')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('exists_on_pbx')->default(true);
                $table->timestamp('last_modified')->nullable();
                $table->uuid('fk_site')->nullable();
                $table->uuid('fk_node')->nullable();
                $table->uuid('fk_fail_over')->nullable();
                
                // Audit fields
                $table->timestamp('created_on')->useCurrent();
                $table->string('created_by', 255)->nullable();
                $table->timestamp('modified_on')->useCurrent();
                $table->string('modified_by', 255)->nullable();
                $table->uuid('created_by_client_id')->nullable();
                $table->uuid('modified_by_client_id')->nullable();
                
                // Sync tracking
                $table->timestamp('synced_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                
                $table->index('fk_employee'); // For joining with CRM agents
                $table->index('reporting');
                $table->index(['created_on', 'created_by_client_id']);
                $table->index('synced_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_config_agents');
    }
};
