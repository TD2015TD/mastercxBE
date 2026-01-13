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
        // Lookup: Day of Week
        Schema::create('mitel_lookup_day_of_week', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('day_name', 50);
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->index('day_name');
        });

        // Lookup: Time Interval
        Schema::create('mitel_lookup_time_interval', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('interval_name', 100);
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->index('interval_name');
        });

        // Config: Agents
        Schema::create('mitel_config_agents', function (Blueprint $table) {
            $table->string('agent_id', 50)->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->string('dialable', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('reporting_id');
        });

        // Config: Queues
        Schema::create('mitel_config_queues', function (Blueprint $table) {
            $table->string('queue_id', 50)->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('reporting_id');
        });

        // Config: DNIS
        Schema::create('mitel_config_dnis', function (Blueprint $table) {
            $table->string('dnis_id', 50)->primary();
            $table->string('name', 255);
            $table->string('reporting_id', 100)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->integer('service_level')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->index('name');
            $table->index('phone_number');
            $table->index('reporting_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_config_dnis');
        Schema::dropIfExists('mitel_config_queues');
        Schema::dropIfExists('mitel_config_agents');
        Schema::dropIfExists('mitel_lookup_time_interval');
        Schema::dropIfExists('mitel_lookup_day_of_week');
    }
};
