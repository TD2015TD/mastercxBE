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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('path', 255);
            $table->string('icon', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('tooltip', 255)->nullable();
            $table->integer('order')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // For additional data like badges, etc.
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('order');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
