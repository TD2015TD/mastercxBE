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
     * v_call_interaction selects customers.email, so we must:
     *   1. Drop the view (CASCADE handles any dependent objects)
     *   2. Alter the column
     *   3. Recreate the view from the canonical SQL file
     */
    public function up(): void
    {
        // Step 1: Drop the dependent view
        DB::statement('DROP VIEW IF EXISTS v_call_interaction CASCADE');

        // Step 2: Alter the column
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // Step 3: Recreate the view from the canonical SQL file
        // The file already contains: DROP VIEW IF EXISTS v_call_interaction CASCADE; CREATE VIEW ...
        $sqlFile = base_path('crm-reporting/sql-views/01_v_call_interaction.sql');
        if (file_exists($sqlFile)) {
            DB::unprepared(file_get_contents($sqlFile));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Drop the view again before reverting the column
        DB::statement('DROP VIEW IF EXISTS v_call_interaction CASCADE');

        // Step 2: Revert the column to NOT NULL
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        // Step 3: Recreate the view (email is still selected; nullability is a DB constraint, not a view concern)
        $sqlFile = base_path('crm-reporting/sql-views/01_v_call_interaction.sql');
        if (file_exists($sqlFile)) {
            DB::unprepared(file_get_contents($sqlFile));
        }
    }
};
