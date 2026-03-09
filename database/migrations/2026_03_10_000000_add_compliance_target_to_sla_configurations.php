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
        Schema::table('sla_configurations', function (Blueprint $table) {
            $table->decimal('compliance_target', 5, 2)->nullable()->after('duration_minutes')
                  ->comment('Target SLA compliance percentage (e.g. 95.00). Null means no target configured.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sla_configurations', function (Blueprint $table) {
            $table->dropColumn('compliance_target');
        });
    }
};
