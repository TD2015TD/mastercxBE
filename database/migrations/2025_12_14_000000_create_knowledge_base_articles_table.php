<?php
// database/migrations/2025_12_14_000000_create_knowledge_base_articles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('category_type')->default('knowledge_base_category');
            $table->string('category_code'); // references lookups.type+code
            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign(['category_type', 'category_code'])
                ->references(['type', 'code'])
                ->on('lookups')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('knowledge_base_articles');
    }
};
