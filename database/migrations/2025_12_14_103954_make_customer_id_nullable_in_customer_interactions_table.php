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
            // Drop the foreign key constraint first
            $table->dropForeign(['customer_id']);

            // Modify the column to be nullable (use unsignedBigInteger for change)
            $table->unsignedBigInteger('customer_id')->nullable()->change();

            // Re-add the foreign key constraint
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_interactions', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['customer_id']);

            // Revert the column to non-nullable
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();

            // Re-add the foreign key constraint
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }
};
