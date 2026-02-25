<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('template_name');
            $table->text('header_content')->nullable();      // Introductory paragraph
            $table->text('body_content')->nullable();        // Main body with placeholders like {{candidate_name}}, {{designation}}, etc.
            $table->text('footer_content')->nullable();      // Closing paragraph / T&C
            $table->string('company_logo_path')->nullable(); // Path to uploaded logo
            $table->string('digital_signature_path')->nullable(); // Path to uploaded signature image
            $table->string('signatory_name')->nullable();    // Name of the signing authority
            $table->string('signatory_designation')->nullable();
            // Salary structure columns
            $table->string('salary_currency')->nullable();
            $table->text('salary_components')->nullable();   // JSON e.g. [{"component":"Basic","calc_type":"percentage","value":50}, ...]
            $table->text('salary_notes')->nullable();
            $table->integer('is_active')->default(1);
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_letter_templates');
    }
};
