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
        Schema::table('citizen_auth_sessions', function (Blueprint $table) {
            $table->string('phone_otp_code', 6)->nullable()->after('otp_code');
        });
    }

    public function down(): void
    {
        Schema::table('citizen_auth_sessions', function (Blueprint $table) {
            $table->dropColumn('phone_otp_code');
        });
    }
};
