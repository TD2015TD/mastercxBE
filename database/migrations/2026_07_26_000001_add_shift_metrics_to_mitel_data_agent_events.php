<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitel_data_agent_events', function (Blueprint $table) {
            $table->timestamp('login_time')->nullable();
            $table->timestamp('logout_time')->nullable();
            $table->integer('shift_duration')->nullable()->default(0);
            $table->integer('wrap_up_duration')->nullable()->default(0);
            $table->integer('acd_count')->nullable()->default(0);
            $table->integer('acd_duration')->nullable()->default(0);
            $table->integer('make_busy_duration')->nullable()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('mitel_data_agent_events', function (Blueprint $table) {
            $table->dropColumn([
                'login_time',
                'logout_time',
                'shift_duration',
                'wrap_up_duration',
                'acd_count',
                'acd_duration',
                'make_busy_duration',
            ]);
        });
    }
};
