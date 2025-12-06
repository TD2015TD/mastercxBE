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
        Schema::create('agent_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade')
                  ->comment('Reference to the agent');
            $table->date('metric_date')
                  ->comment('Date for this performance snapshot');
            
            // Ticket Performance
            $table->integer('tickets_resolved')->default(0)
                  ->comment('Number of tickets resolved on this date');
            $table->integer('tickets_assigned')->default(0)
                  ->comment('Number of tickets assigned on this date');
            $table->integer('tickets_pending')->default(0)
                  ->comment('Number of pending tickets at end of date');
            $table->integer('daily_target')->default(0)
                  ->comment('Daily resolution target');
            $table->decimal('target_achievement_rate', 5, 2)->default(0)
                  ->comment('Target achievement percentage (0-100)');
            
            // Response & Resolution Metrics (in seconds)
            $table->integer('avg_first_response_time')->default(0)
                  ->comment('Average first response time in seconds');
            $table->integer('avg_resolution_time')->default(0)
                  ->comment('Average resolution time in seconds');
            $table->integer('avg_response_time')->default(0)
                  ->comment('Average response time in seconds');
            
            // Quality Metrics
            $table->decimal('sla_compliance_rate', 5, 2)->default(0)
                  ->comment('SLA compliance percentage (0-100)');
            $table->decimal('customer_satisfaction_score', 3, 2)->default(0)
                  ->comment('Customer satisfaction score (0-5)');
            $table->integer('customer_satisfaction_count')->default(0)
                  ->comment('Number of satisfaction ratings received');
            $table->integer('reopened_tickets')->default(0)
                  ->comment('Number of tickets reopened');
            
            // Productivity Metrics
            $table->integer('total_interactions')->default(0)
                  ->comment('Total customer interactions (emails, chats, calls)');
            $table->integer('avg_handle_time')->default(0)
                  ->comment('Average handle time in seconds');
            $table->decimal('first_contact_resolution_rate', 5, 2)->default(0)
                  ->comment('First contact resolution percentage (0-100)');
            
            $table->timestamps();
            
            // Composite unique index: one record per agent per date
            $table->unique(['agent_id', 'metric_date'], 'agent_metric_date_unique');
            
            // Indexes for common queries
            $table->index('metric_date');
            $table->index(['agent_id', 'metric_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_performance_metrics');
    }
};
