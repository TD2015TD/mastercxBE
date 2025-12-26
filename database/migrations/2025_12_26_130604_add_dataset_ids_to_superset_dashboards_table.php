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
        Schema::table('superset_dashboards', function (Blueprint $table) {
            $table->json('dataset_ids')->nullable()->after('rls_config')
                ->comment('Array of Superset dataset IDs for RLS filtering');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('superset_dashboards', function (Blueprint $table) {
            $table->dropColumn('dataset_ids');
        });
    }
};
