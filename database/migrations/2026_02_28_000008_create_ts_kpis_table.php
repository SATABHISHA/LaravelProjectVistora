<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ts_kpis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('period', 7); // Format: YYYY-MM
            $table->string('metric_name');
            $table->decimal('metric_value', 8, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('ts_users')->onDelete('cascade');
            $table->unique(['user_id', 'period', 'metric_name']);
            $table->index('user_id');
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ts_kpis');
    }
};
