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
        Schema::create('employment_details', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('company_name');
            $table->string('dateOfJoining');
            $table->string('EmpCode');
            $table->string('BiometricId')->nullable();
            $table->string('BusinessUnit')->nullable();
            $table->string('Department')->nullable();
            $table->string('SubDepartment')->nullable();
            $table->string('Designation')->nullable();
            $table->string('Region')->nullable();
            $table->string('Branch')->nullable();
            $table->string('SubBranch')->nullable();
            $table->string('EmploymentType')->nullable();
            $table->string('EmploymentStatus')->nullable();
            $table->string('ConfirmationStatus')->nullable();
            $table->string('ReportingManager')->nullable();
            $table->string('FunctionalManager')->nullable();
            $table->string('ReportingManager3')->nullable(); // <-- Add this line
            $table->string('PFNumber')->nullable();
            $table->string('UAN')->nullable();
            $table->string('EmployeeContributionLimit')->nullable();
            $table->string('EmployerContributionLimit')->nullable();
            $table->string('PensionNumber')->nullable();
            $table->integer('PF')->nullable();
            $table->integer('Gratuity')->nullable();
            $table->integer('DraftYN')->nullable();
            $table->integer('ActiveYn')->default(1); // <-- Added field
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_details');
    }
};
