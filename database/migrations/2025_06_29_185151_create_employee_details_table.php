<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_details', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('EmpCode');
            $table->string('prefix')->nullable();
            $table->string('FirstName');
            $table->string('MiddleName')->nullable();
            $table->string('LastName');
            $table->string('MaritalStatus')->nullable();
            $table->string('DOB');
            $table->string('Gender')->nullable();
            $table->string('BloodGroup')->nullable();
            $table->string('Nationality')->nullable();
            $table->string('WorkEmail')->nullable();
            $table->string('Mobile')->nullable();
            $table->string('SkillType')->nullable();
            $table->string('Pan')->nullable();
            $table->string('Adhaar')->nullable();
            $table->string('Passport')->nullable();
            $table->string('PassportExpiryDate')->nullable();
            $table->string('PersonalEmail')->nullable();
            $table->string('EmgContactName')->nullable();
            $table->string('EmgNumber')->nullable();
            $table->string('EmgContactRelation')->nullable();
            $table->string('PmntAddress')->nullable();
            $table->string('PmntState')->nullable();
            $table->string('PmntCity')->nullable();
            $table->string('PmntPincode')->nullable();
            $table->string('CrntAddress')->nullable();
            $table->string('CrntState')->nullable();
            $table->string('CrntCity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_details');
    }
};
