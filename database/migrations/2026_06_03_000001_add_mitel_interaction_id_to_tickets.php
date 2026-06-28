<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('mitel_interaction_id', 100)->nullable()->after('case_id');
            $table->index('mitel_interaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['mitel_interaction_id']);
            $table->dropColumn('mitel_interaction_id');
        });
    }
};
