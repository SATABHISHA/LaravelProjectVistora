<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->string('current_location')->nullable();
            $table->string('highest_qualification')->nullable();
            $table->integer('total_experience_years')->nullable();
            $table->decimal('current_ctc', 15, 2)->nullable();
            $table->decimal('expected_ctc', 15, 2)->nullable();
            $table->string('notice_period')->nullable();  // e.g., "30 days"
            $table->string('resume_path')->nullable();    // stored file path
            $table->string('linkedin_url')->nullable();
            $table->string('source')->nullable();          // Referral, LinkedIn, Job Portal, Walk-in
            $table->string('referred_by')->nullable();
            $table->text('skills')->nullable();
            $table->string('status')->default('Active');   // Active, Blacklisted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidates');
    }
};
