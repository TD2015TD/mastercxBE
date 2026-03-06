<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes customer email optional (nullable) to allow ticket creation without an email.
     *
     * PostgreSQL blocks ALTER COLUMN on tables referenced by views.
     * Multiple views select customers.email, so we must:
     *   1. Drop ALL dependent views (CASCADE)
     *   2. Alter the column
     *   3. Recreate all views from the canonical SQL files
     */
    public function up(): void
    {
        // Step 1: Drop all views that reference customers.email
        DB::statement('DROP VIEW IF EXISTS v_call_interaction CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_ticketing_report CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_call_disposition CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_transfer_escalation CASCADE');

        // Step 2: Alter the column
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // Step 3: Recreate all views from canonical SQL files (in dependency order)
        $viewFiles = [
            'crm-reporting/sql-views/01_v_call_interaction.sql',
            'crm-reporting/sql-views/02_v_ticketing_report.sql',
            'crm-reporting/sql-views/04_v_call_disposition.sql',
            'crm-reporting/sql-views/05_v_transfer_escalation.sql',
        ];

        foreach ($viewFiles as $relPath) {
            $sqlFile = base_path($relPath);
            if (file_exists($sqlFile)) {
                DB::unprepared(file_get_contents($sqlFile));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Drop all dependent views before reverting the column
        DB::statement('DROP VIEW IF EXISTS v_call_interaction CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_ticketing_report CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_call_disposition CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_transfer_escalation CASCADE');

        // Step 2: Revert the column to NOT NULL
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        // Step 3: Recreate all views
        $viewFiles = [
            'crm-reporting/sql-views/01_v_call_interaction.sql',
            'crm-reporting/sql-views/02_v_ticketing_report.sql',
            'crm-reporting/sql-views/04_v_call_disposition.sql',
            'crm-reporting/sql-views/05_v_transfer_escalation.sql',
        ];

        foreach ($viewFiles as $relPath) {
            $sqlFile = base_path($relPath);
            if (file_exists($sqlFile)) {
                DB::unprepared(file_get_contents($sqlFile));
            }
        }
    }
};
