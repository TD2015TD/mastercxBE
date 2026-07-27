<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitel_data_mcc_queue_performance', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('queue_id', 50)->nullable()->index();
            $table->date('period_date')->nullable()->index();
            $table->integer('offered')->nullable()->default(0);
            $table->integer('delivered_to_queue')->nullable()->default(0);
            $table->integer('service_count')->nullable()->default(0);
            $table->integer('completed_count')->nullable()->default(0);
            $table->integer('completed_time_to_answer')->nullable()->default(0);
            $table->integer('completed_duration')->nullable()->default(0);
            $table->integer('short_abandon')->nullable()->default(0);
            $table->integer('long_abandon')->nullable()->default(0);
            $table->integer('time_to_abandon')->nullable()->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('mitel_data_queue_spectrum', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('queue_id', 50)->nullable()->index();
            $table->timestamp('period_date')->nullable()->index();
            $table->integer('spectrum_type_id')->nullable();
            $table->integer('total_calls')->nullable()->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitel_data_mcc_queue_performance');
        Schema::dropIfExists('mitel_data_queue_spectrum');
    }
};
