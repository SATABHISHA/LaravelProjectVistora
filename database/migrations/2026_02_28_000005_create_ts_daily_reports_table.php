<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ts_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->date('report_date');
            $table->text('description');
            $table->decimal('hours_spent', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('ts_users')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('ts_tasks')->onDelete('set null');
            $table->index('user_id');
            $table->index('report_date');
            $table->unique(['user_id', 'task_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ts_daily_reports');
    }
};
