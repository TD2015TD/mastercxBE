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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('contact_type_id')->constrained('lookups')->onDelete('restrict');
            $table->string('value', 255); // Phone number, email, fax, etc.
            $table->boolean('is_primary')->default(false); // Mark as primary contact
            $table->text('notes')->nullable(); // Optional notes (e.g., "Mobile", "Work", "Personal")
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->unique(['customer_id', 'contact_type_id', 'value']); // Prevent duplicate contacts
            $table->index(['customer_id', 'contact_type_id']);
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
