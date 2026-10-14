<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The escalation workflow (Resolve action, EW-04) is meant to move a status to "resolved",
     * matching the action's own name and every other resolve-flavoured status in the app. The
     * enum instead stored 'completed', so anything checking for the literal word "resolved" —
     * QA scripts, future UI text — never matched what the API actually returned.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE escalations DROP CONSTRAINT escalations_status_check");
        DB::statement("ALTER TABLE escalations ALTER COLUMN status SET DEFAULT 'pending'");
        DB::table('escalations')->where('status', 'completed')->update(['status' => 'resolved']);
        DB::statement("ALTER TABLE escalations ADD CONSTRAINT escalations_status_check CHECK (status IN ('pending', 'acknowledged', 'resolved'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE escalations DROP CONSTRAINT escalations_status_check");
        DB::table('escalations')->where('status', 'resolved')->update(['status' => 'completed']);
        DB::statement("ALTER TABLE escalations ADD CONSTRAINT escalations_status_check CHECK (status IN ('pending', 'acknowledged', 'completed'))");
    }
};
