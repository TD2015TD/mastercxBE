<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kbase_share_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('interaction_id');          // Mitel conversation / session ID
            $table->string('article_id');              // KBase article ID
            $table->string('article_title')->nullable();
            $table->text('signed_url')->nullable();    // The generated signed URL sent to customer
            $table->timestamp('shared_at')->useCurrent();

            $table->index('interaction_id');
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kbase_share_logs');
    }
};
