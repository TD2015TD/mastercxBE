<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reporting views may still reference customers.phone_number.
        // Drop dependent views first so the column can be removed.
        DB::statement('DROP VIEW IF EXISTS v_agent_group_performance_by_period');
        DB::statement('DROP VIEW IF EXISTS v_call_interaction');

        if (Schema::hasColumn('customers', 'phone_number')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('phone_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'phone_number')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('phone_number', 20)->after('email');
            });
        }
    }
};
