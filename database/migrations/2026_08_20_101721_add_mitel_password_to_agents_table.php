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
            // Stores the agent's real Mitel password, encrypted at rest via
            // the model's 'encrypted' cast (Laravel encrypt()/decrypt(),
            // keyed off APP_KEY). Needed because the app-level impersonation
            // token (client_credentials grant + Impersonate header) Mitel
            // issues is NOT permitted to change ACD presence/state — Mitel's
            // server ties that token's primary identity to our OAuth client
            // ("CXCRM"), not the agent, and silently no-ops any state PUT
            // sent with it. A grant_type=password login (this column) makes
            // the agent themselves the token's principal, which Mitel's own
            // /AuthorizationServer/Token confirmed supports.
            $table->text('mitel_password')->nullable()->after('agent_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('mitel_password');
        });
    }
};
