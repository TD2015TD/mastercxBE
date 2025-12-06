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
        Schema::create('agent_call_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade')
                  ->comment('Reference to the agent');
            $table->date('statistic_date')
                  ->comment('Date for this daily statistics snapshot');
            
            // Call Volume Statistics
            $table->integer('total_calls')->default(0)
                  ->comment('Total calls handled on this date');
            $table->integer('answered_calls')->default(0)
                  ->comment('Calls answered on this date');
            $table->integer('missed_calls')->default(0)
                  ->comment('Calls missed on this date');
            $table->integer('transferred_calls')->default(0)
                  ->comment('Calls transferred to other agents');
            $table->integer('outbound_calls')->default(0)
                  ->comment('Outbound calls made');
            
            // Call Duration Statistics (in seconds)
            $table->integer('total_talk_time')->default(0)
                  ->comment('Total talk time in seconds');
            $table->integer('avg_call_duration')->default(0)
                  ->comment('Average call duration in seconds');
            $table->integer('longest_call_duration')->default(0)
                  ->comment('Longest call duration in seconds');
            $table->integer('shortest_call_duration')->nullable()
                  ->comment('Shortest call duration in seconds (null if no calls)');
            
            // After Call Work Statistics
            $table->integer('total_acw_time')->default(0)
                  ->comment('Total after-call work time in seconds');
            $table->integer('avg_acw_time')->default(0)
                  ->comment('Average after-call work time in seconds');
            
            // Availability Statistics (in seconds)
            $table->integer('available_time')->default(0)
                  ->comment('Total time in Available state (seconds)');
            $table->integer('busy_time')->default(0)
                  ->comment('Total time in Busy state (seconds)');
            $table->integer('away_time')->default(0)
                  ->comment('Total time in Away state (seconds)');
            $table->integer('dnd_time')->default(0)
                  ->comment('Total time in Do Not Disturb state (seconds)');
            $table->integer('logged_in_time')->default(0)
                  ->comment('Total time logged into Mitel (seconds)');
            
            // Performance Metrics
            $table->decimal('answer_rate', 5, 2)->default(0)
                  ->comment('Percentage of calls answered (0-100)');
            $table->decimal('occupancy_rate', 5, 2)->default(0)
                  ->comment('Percentage of time occupied with calls and ACW (0-100)');
            $table->decimal('utilization_rate', 5, 2)->default(0)
                  ->comment('Percentage of logged-in time spent on calls (0-100)');
            
            $table->timestamps();
            
            // Composite unique index: one record per agent per date
            $table->unique(['agent_id', 'statistic_date'], 'agent_date_unique');
            
            // Indexes for common queries
            $table->index('statistic_date');
            $table->index(['agent_id', 'statistic_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_call_statistics');
    }
};
