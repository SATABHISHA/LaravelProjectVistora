<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_applications', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->unsignedBigInteger('job_posting_id');
            $table->unsignedBigInteger('candidate_id');
            $table->date('applied_date')->nullable();
            $table->string('current_stage')->nullable();   // e.g., "Screening", "Interview Round 1"
            $table->string('status')->default('Applied');  // Applied, In Progress, Selected, Rejected, On Hold, Offer Sent, Joined
            $table->text('overall_remarks')->nullable();
            $table->string('final_decision')->nullable();  // Selected, Rejected
            $table->string('decided_by')->nullable();
            $table->timestamp('decision_date')->nullable();
            $table->timestamps();

            $table->foreign('job_posting_id')->references('id')->on('recruitment_job_postings')->onDelete('cascade');
            $table->foreign('candidate_id')->references('id')->on('recruitment_candidates')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_applications');
    }
};
