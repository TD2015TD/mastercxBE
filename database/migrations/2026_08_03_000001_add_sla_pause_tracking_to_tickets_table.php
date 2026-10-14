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
        Schema::table('tickets', function (Blueprint $table) {
            // Set while the SLA clock is frozen (e.g. On Hold). Null means the clock is running.
            $table->timestamp('sla_paused_at')->nullable()->after('sla_remaining_minutes');

            // Total time the clock has been frozen so far, kept so the deadline survives a
            // re-target (priority change) without swallowing previously accrued hold time.
            $table->integer('sla_paused_minutes')->default(0)->after('sla_paused_at');

            $table->index('sla_paused_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['sla_paused_at']);
            $table->dropColumn(['sla_paused_at', 'sla_paused_minutes']);
        });
    }
};
