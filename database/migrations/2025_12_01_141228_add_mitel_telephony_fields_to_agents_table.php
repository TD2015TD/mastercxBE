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
        Schema::table('agents', function (Blueprint $table) {
            // Mitel Telephony State Management - Real-time State Only
            $table->string('mitel_state')->nullable()->after('mitel_queue_ids')
                  ->comment('Current Mitel state: Available, Busy, DoNotDisturb, Away, Logout');
            $table->string('mitel_state_reason')->nullable()->after('mitel_state')
                  ->comment('Reason for state change (e.g., Break, Training)');
            $table->boolean('mitel_logged_in')->default(false)->after('mitel_state_reason')
                  ->comment('Is agent currently logged into Mitel');
            $table->timestamp('mitel_last_state_change')->nullable()->after('mitel_logged_in')
                  ->comment('Timestamp of last Mitel state change');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'mitel_state',
                'mitel_state_reason',
                'mitel_logged_in',
                'mitel_last_state_change',
            ]);
        });
    }
};
