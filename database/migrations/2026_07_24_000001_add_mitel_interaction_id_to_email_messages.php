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
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('mitel_interaction_id')->nullable()->after('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn('mitel_interaction_id');
        });
    }
};
