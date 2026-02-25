<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('job_title');
            $table->string('department')->nullable();
            $table->string('sub_department')->nullable();
            $table->string('designation')->nullable();
            $table->string('location')->nullable();
            $table->string('employment_type')->nullable();  // Full-time, Part-time, Contract
            $table->integer('no_of_openings')->default(1);
            $table->text('job_description')->nullable();
            $table->text('requirements')->nullable();
            $table->decimal('min_salary', 15, 2)->nullable();
            $table->decimal('max_salary', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->date('application_deadline')->nullable();
            $table->string('status')->default('Open');   // Open, Closed, On Hold
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_job_postings');
    }
};
