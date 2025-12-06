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
        Schema::table('agents', function (Blueprint $table) {
            // Remove performance metric fields (moved to agent_performance_metrics table)
            $table->dropColumn([
                'tickets_resolved_today',
                'daily_target',
                'avg_response_time',
                'sla_compliance_rate',
                'customer_satisfaction',
            ]);
            
            // Remove schedule fields (moved to agent_shifts table)
            $table->dropColumn([
                'shift_start',
                'shift_end',
                'working_days',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Restore performance metric fields
            $table->integer('tickets_resolved_today')->default(0)->after('max_concurrent_tickets');
            $table->integer('daily_target')->default(0)->after('tickets_resolved_today');
            $table->decimal('avg_response_time', 8, 2)->default(0)->after('daily_target');
            $table->decimal('sla_compliance_rate', 5, 2)->default(0)->after('avg_response_time');
            $table->decimal('customer_satisfaction', 3, 2)->default(0)->after('sla_compliance_rate');
            
            // Restore schedule fields
            $table->time('shift_start')->nullable()->after('customer_satisfaction');
            $table->time('shift_end')->nullable()->after('shift_start');
            $table->json('working_days')->nullable()->after('shift_end');
        });
    }
};
