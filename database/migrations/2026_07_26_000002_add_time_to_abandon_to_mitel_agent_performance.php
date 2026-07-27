<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitel_agent_performance_by_period', function (Blueprint $table) {
            $table->integer('agent_time_to_abandon')->nullable()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('mitel_agent_performance_by_period', function (Blueprint $table) {
            $table->dropColumn('agent_time_to_abandon');
        });
    }
};
