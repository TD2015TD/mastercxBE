<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])->default('draft')->after('author');
            $table->unsignedBigInteger('uploaded_by')->nullable()->after('status');
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn(['status', 'uploaded_by']);
        });
    }
};
