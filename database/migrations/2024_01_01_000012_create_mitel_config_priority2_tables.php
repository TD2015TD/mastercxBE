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
        // Config: Account Code
        Schema::create('mitel_config_account_codes', function (Blueprint $table) {
            $table->uuid('account_code_id')->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->string('account_code', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('account_code');
            $table->index('reporting_id');
        });

        // Config: ANI (Automatic Number Identification)
        Schema::create('mitel_config_ani', function (Blueprint $table) {
            $table->uuid('ani_id')->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('phone_number');
            $table->index('reporting_id');
        });

        // Config: Make Busy
        Schema::create('mitel_config_make_busy', function (Blueprint $table) {
            $table->uuid('make_busy_id')->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('reason_code');
            $table->index('reporting_id');
        });

        // Config: Agent Group
        Schema::create('mitel_config_agent_groups', function (Blueprint $table) {
            $table->uuid('agent_group_id')->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('reporting_id');
        });

        // Config: Extension
        Schema::create('mitel_config_extensions', function (Blueprint $table) {
            $table->uuid('extension_id')->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->string('extension_number', 50)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('extension_number');
            $table->index('reporting_id');
        });

        // Config: Trunk
        Schema::create('mitel_config_trunks', function (Blueprint $table) {
            $table->uuid('trunk_id')->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('reporting_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_config_trunks');
        Schema::dropIfExists('mitel_config_extensions');
        Schema::dropIfExists('mitel_config_agent_groups');
        Schema::dropIfExists('mitel_config_make_busy');
        Schema::dropIfExists('mitel_config_ani');
        Schema::dropIfExists('mitel_config_account_codes');
    }
};
