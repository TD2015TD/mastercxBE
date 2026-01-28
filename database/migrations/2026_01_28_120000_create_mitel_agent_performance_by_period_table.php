<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores granular agent performance metrics from Mitel's 
     * AgentPerformanceByPeriodStats view, providing detailed breakdowns 
     * of ACD, Non-ACD, Outbound calls with internal/external classification.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mitel_agent_performance_by_period', function (Blueprint $table) {
            // === PRIMARY KEY ===
            // Composite key: agent + time interval
            $table->uuid('agent_id')->index();
            $table->timestamp('interval_start_time')->index();
            $table->date('midnight_start_date')->index();
            
            // === AGENT IDENTIFICATION ===
            $table->string('agent_reporting', 50)->nullable()->index();
            $table->string('employee_id', 100)->nullable()->index(); // Maps to agents.employee_id
            
            // === TIME DIMENSIONS ===
            $table->string('day_of_week_name', 20)->nullable();
            $table->integer('interval_number')->nullable();
            
            // === ACD CALLS (Automatic Call Distribution) ===
            // Total ACD metrics
            $table->integer('agent_acd_count')->default(0);
            $table->integer('agent_acd_duration')->default(0); // seconds
            $table->integer('agent_acd_time_to_answer')->default(0); // seconds
            
            // Internal ACD calls (within organization)
            $table->integer('agent_acd_internal_count')->default(0);
            $table->integer('agent_acd_internal_duration')->default(0);
            $table->integer('agent_acd_internal_time_to_answer')->default(0);
            
            // External ACD calls (from outside)
            $table->integer('agent_acd_external_count')->default(0);
            $table->integer('agent_acd_external_duration')->default(0);
            $table->integer('agent_acd_external_time_to_answer')->default(0);
            
            // === NON-ACD CALLS (Direct calls, not through queue) ===
            // Total Non-ACD metrics
            $table->integer('agent_non_acd_count')->default(0);
            $table->integer('agent_non_acd_duration')->default(0);
            $table->integer('agent_non_acd_time_to_answer')->default(0);
            
            // Internal Non-ACD calls
            $table->integer('agent_non_acd_internal_count')->default(0);
            $table->integer('agent_non_acd_internal_duration')->default(0);
            $table->integer('agent_non_acd_internal_time_to_answer')->default(0);
            
            // External Non-ACD calls
            $table->integer('agent_non_acd_external_count')->default(0);
            $table->integer('agent_non_acd_external_duration')->default(0);
            $table->integer('agent_non_acd_external_time_to_answer')->default(0);
            
            // === OUTBOUND CALLS ===
            // Total Outbound metrics
            $table->integer('agent_outbound_count')->default(0);
            $table->integer('agent_outbound_duration')->default(0);
            
            // Internal Outbound calls
            $table->integer('agent_outbound_internal_count')->default(0);
            $table->integer('agent_outbound_internal_duration')->default(0);
            
            // External Outbound calls
            $table->integer('agent_outbound_external_count')->default(0);
            $table->integer('agent_outbound_external_duration')->default(0);
            
            // === TRANSFER & CONFERENCE ===
            $table->integer('agent_transfer_count')->default(0);
            $table->integer('agent_conference_count')->default(0);
            
            // === ABANDONED CALLS ===
            $table->integer('agent_abandoned_count')->default(0);
            
            // === AFTER CALL WORK (ACW) / WRAP-UP ===
            $table->integer('agent_acw_duration')->default(0); // seconds
            
            // === AVAILABILITY & OCCUPANCY ===
            $table->integer('agent_available_duration')->default(0); // seconds ready to take calls
            $table->integer('agent_unavailable_duration')->default(0); // seconds not available
            
            // === BUSY REASONS ===
            $table->integer('agent_busy_duration')->default(0); // seconds in manual busy state
            $table->integer('agent_system_busy_duration')->default(0); // seconds system-busy
            
            // === LOGIN / LOGOUT ===
            $table->integer('agent_login_duration')->default(0); // total logged-in seconds
            $table->timestamp('agent_first_login_time')->nullable();
            $table->timestamp('agent_last_logout_time')->nullable();
            
            // === QUALITY METRICS ===
            // Average speed of answer for ACD calls
            $table->decimal('avg_speed_of_answer', 10, 2)->nullable();
            
            // Average handle time (talk + acw)
            $table->decimal('avg_handle_time', 10, 2)->nullable();
            
            // Occupancy rate (busy time / login time)
            $table->decimal('occupancy_rate', 5, 2)->nullable();
            
            // === SYNC METADATA ===
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            // === COMPOSITE PRIMARY KEY ===
            $table->primary(['agent_id', 'interval_start_time'], 'pk_agent_performance');
            
            // === INDEXES FOR PERFORMANCE ===
            // Time-based queries
            $table->index(['midnight_start_date', 'agent_id'], 'idx_date_agent');
            $table->index(['interval_start_time', 'agent_id'], 'idx_interval_agent');
            
            // Agent-based queries
            $table->index(['employee_id', 'midnight_start_date'], 'idx_employee_date');
            $table->index(['agent_reporting', 'midnight_start_date'], 'idx_reporting_date');
            
            // Metric-based queries
            $table->index('agent_acd_count');
            $table->index('agent_outbound_count');
            
            // Day of week analysis
            $table->index(['day_of_week_name', 'midnight_start_date'], 'idx_dow_date');
        });

        // Add comment to table
        DB::statement("COMMENT ON TABLE mitel_agent_performance_by_period IS 'Granular agent performance metrics from Mitel AgentPerformanceByPeriodStats view - provides detailed interval-based metrics for ACD, Non-ACD, and Outbound calls with internal/external breakdowns'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mitel_agent_performance_by_period');
    }
};
