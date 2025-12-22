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
            $table->dropUnique(['superset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('superset_dashboards', function (Blueprint $table) {
            $table->unique('superset_id');
        });
    }
};
