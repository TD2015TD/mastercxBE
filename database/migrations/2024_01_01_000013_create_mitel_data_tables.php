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
        // Data: Agent Performance By Period
        Schema::create('mitel_data_agent_performance', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('agent_id', 50)->nullable()->index();
            $table->timestamp('period_date')->nullable()->index();
            $table->string('day_of_week_id', 50)->nullable()->index();
            $table->string('time_interval_id', 50)->nullable()->index();
            $table->integer('acd_count')->nullable()->default(0);
            $table->integer('acd_duration')->nullable()->default(0);
            $table->integer('shift_duration')->nullable()->default(0);
            $table->integer('idle_duration')->nullable()->default(0);
            $table->integer('make_busy_count')->nullable()->default(0);
            $table->integer('make_busy_duration')->nullable()->default(0);
            $table->timestamp('synced_at')->nullable();
            
            $table->index(['agent_id', 'period_date']);
            $table->foreign('agent_id')->references('agent_id')->on('mitel_config_agents')->onDelete('cascade');
            $table->foreign('day_of_week_id')->references('id')->on('mitel_lookup_day_of_week')->onDelete('set null');
            $table->foreign('time_interval_id')->references('id')->on('mitel_lookup_time_interval')->onDelete('set null');
        });

        // Data: Queue Performance By Period
        Schema::create('mitel_data_queue_performance', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('queue_id', 50)->nullable()->index();
            $table->timestamp('period_date')->nullable()->index();
            $table->string('day_of_week_id', 50)->nullable()->index();
            $table->string('time_interval_id', 50)->nullable()->index();
            $table->integer('acd_count')->nullable()->default(0);
            $table->integer('acd_duration')->nullable()->default(0);
            $table->integer('abandon_count')->nullable()->default(0);
            $table->integer('service_level')->nullable()->default(0);
            $table->integer('max_waiting_calls')->nullable()->default(0);
            $table->timestamp('synced_at')->nullable();
            
            $table->index(['queue_id', 'period_date']);
            $table->foreign('queue_id')->references('queue_id')->on('mitel_config_queues')->onDelete('cascade');
            $table->foreign('day_of_week_id')->references('id')->on('mitel_lookup_day_of_week')->onDelete('set null');
            $table->foreign('time_interval_id')->references('id')->on('mitel_lookup_time_interval')->onDelete('set null');
        });

        // Data: Inbound Trace
        Schema::create('mitel_data_inbound_trace', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('agent_id', 50)->nullable()->index();
            $table->string('queue_id', 50)->nullable()->index();
            $table->string('dnis_id', 50)->nullable()->index();
            $table->string('ani_id', 50)->nullable()->index();
            $table->timestamp('call_date')->nullable()->index();
            $table->integer('duration')->nullable()->default(0);
            $table->integer('time_to_answer')->nullable()->default(0);
            $table->string('digits_dialed', 50)->nullable();
            $table->string('call_id', 100)->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            
            $table->index(['call_date', 'agent_id']);
            $table->index(['call_date', 'queue_id']);
            $table->foreign('agent_id')->references('agent_id')->on('mitel_config_agents')->onDelete('set null');
            $table->foreign('queue_id')->references('queue_id')->on('mitel_config_queues')->onDelete('set null');
            $table->foreign('dnis_id')->references('dnis_id')->on('mitel_config_dnis')->onDelete('set null');
            $table->foreign('ani_id')->references('ani_id')->on('mitel_config_ani')->onDelete('set null');
        });

        // Data: Outbound Trace
        Schema::create('mitel_data_outbound_trace', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('agent_id', 50)->nullable()->index();
            $table->string('account_code_id', 50)->nullable()->index();
            $table->timestamp('call_date')->nullable()->index();
            $table->integer('duration')->nullable()->default(0);
            $table->string('digits_dialed', 50)->nullable();
            $table->string('call_id', 100)->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            
            $table->index(['call_date', 'agent_id']);
            $table->foreign('agent_id')->references('agent_id')->on('mitel_config_agents')->onDelete('set null');
            $table->foreign('account_code_id')->references('account_code_id')->on('mitel_config_account_codes')->onDelete('set null');
        });

        // Data: Agent Trace Events
        Schema::create('mitel_data_agent_trace_events', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('agent_id', 50)->nullable()->index();
            $table->timestamp('event_start_date')->nullable()->index();
            $table->timestamp('event_end_date')->nullable()->index();
            $table->string('acd_event_id', 50)->nullable();
            $table->string('make_busy_id', 50)->nullable()->index();
            $table->string('queue_id', 50)->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            
            $table->index(['agent_id', 'event_start_date']);
            $table->foreign('agent_id')->references('agent_id')->on('mitel_config_agents')->onDelete('cascade');
            $table->foreign('make_busy_id')->references('make_busy_id')->on('mitel_config_make_busy')->onDelete('set null');
            $table->foreign('queue_id')->references('queue_id')->on('mitel_config_queues')->onDelete('set null');
        });

        // Data: Agent Events
        Schema::create('mitel_data_agent_events', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('agent_id', 50)->nullable()->index();
            $table->timestamp('event_date')->nullable()->index();
            $table->string('event_type', 100)->nullable();
            $table->integer('duration')->nullable()->default(0);
            $table->timestamp('synced_at')->nullable();
            
            $table->index(['agent_id', 'event_date']);
            $table->foreign('agent_id')->references('agent_id')->on('mitel_config_agents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_data_agent_events');
        Schema::dropIfExists('mitel_data_agent_trace_events');
        Schema::dropIfExists('mitel_data_outbound_trace');
        Schema::dropIfExists('mitel_data_inbound_trace');
        Schema::dropIfExists('mitel_data_queue_performance');
        Schema::dropIfExists('mitel_data_agent_performance');
    }
};
