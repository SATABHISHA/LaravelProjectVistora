<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_stage_results', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('stage_id');
            $table->string('stage_name')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('conducted_at')->nullable();
            $table->string('interviewer_emp_code')->nullable();
            $table->string('interviewer_name')->nullable();
            $table->text('remarks')->nullable();
            $table->integer('rating')->nullable();     // 1–10
            $table->string('outcome')->nullable();     // Pass, Fail, On Hold, No Show
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('recruitment_applications')->onDelete('cascade');
            $table->foreign('stage_id')->references('id')->on('recruitment_stages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_stage_results');
    }
};
