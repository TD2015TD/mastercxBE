<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MonitorSlaCommand::shouldAutoEscalate() reads $slaConfig->auto_escalate, but this
        // column never existed — auto-escalation could never trigger for any ticket.
        Schema::table('sla_configurations', function (Blueprint $table) {
            $table->boolean('auto_escalate')->default(true)->after('escalation_threshold_percent');
        });

        // SlaAlertService::escalateTicket() passes escalated_by => null for system-triggered
        // (non-human) escalations, but the column was a required FK — every auto-escalation
        // attempt failed with a NOT NULL violation. doctrine/dbal isn't installed, so raw SQL
        // instead of Blueprint::change().
        DB::statement('ALTER TABLE escalations ALTER COLUMN escalated_by DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('sla_configurations', function (Blueprint $table) {
            $table->dropColumn('auto_escalate');
        });

        DB::statement('ALTER TABLE escalations ALTER COLUMN escalated_by SET NOT NULL');
    }
};
