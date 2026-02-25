<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_letters', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('template_id');
            $table->string('offer_reference_no')->nullable(); // Auto-generated reference
            $table->string('candidate_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->decimal('ctc_annual', 15, 2)->nullable();
            $table->text('salary_breakdown')->nullable();     // JSON of actual salary figures
            $table->text('rendered_content')->nullable();     // Final HTML content of the letter
            $table->string('pdf_path')->nullable();           // Path to generated PDF
            $table->string('status')->default('Draft');       // Draft, Sent, Accepted, Declined, Revoked
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('generated_by')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('recruitment_applications')->onDelete('cascade');
            $table->foreign('candidate_id')->references('id')->on('recruitment_candidates')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('offer_letter_templates')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_letters');
    }
};
