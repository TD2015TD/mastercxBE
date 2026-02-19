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
        Schema::table('customer_interactions', function (Blueprint $table) {
            // Add interaction_type_id to reference lookup table for interaction types (with bilingual support)
            $table->foreignId('interaction_type_id')->nullable()->after('interaction_type')->constrained('lookups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_interactions', function (Blueprint $table) {
            $table->dropForeignIdFor('lookups', 'interaction_type_id');
            $table->dropColumn('interaction_type_id');
        });
    }
};
