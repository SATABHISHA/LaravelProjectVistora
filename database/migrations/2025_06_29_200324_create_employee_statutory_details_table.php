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
        Schema::create('employee_statutory_details', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('EmpCode');
            $table->string('TaxRegime')->nullable();
            $table->integer('AdhaarPanLinkedYN')->nullable();
            $table->integer('ProvidentFundYN')->nullable();
            $table->string('PFNo')->nullable();
            $table->string('UAN')->nullable();
            $table->string('EmpPFContbtnLmt')->nullable();
            $table->string('EmployerPFContbtnLmt')->nullable();
            $table->integer('PensionYN')->nullable();
            $table->integer('EmpStateInsuranceYN')->nullable();
            $table->string('EmpStateInsNo')->nullable();
            $table->string('EmpStateInsDispensaryName')->nullable();
            $table->string('ESISubUnitCode')->nullable();
            $table->integer('LabourWelfareFundYN')->nullable();
            $table->integer('PTYN')->nullable();
            $table->integer('BonusYN')->nullable();
            $table->integer('GratuityYN')->nullable();
            $table->integer('GratuityInCtcYN')->nullable();
            $table->string('DateOfJoin')->nullable();
            $table->integer('VoluntaryPfYN')->nullable();
            $table->string('VoluntaryPFAmount')->nullable();
            $table->string('VoluntaryPFPercent')->nullable();
            $table->string('VoluntaryPFEffectiveDate')->nullable();
            $table->integer('EmployerCtbnToNPSYN')->nullable();
            $table->string('EmployerAmount')->nullable();
            $table->string('EmployerPercentage')->nullable();
            $table->string('EmployerPanNumber')->nullable();
            $table->string('SalaryMode')->nullable();
            $table->string('SalaryBank')->nullable();
            $table->string('ReimbursementMode')->nullable();
            $table->string('ReimbursementBank')->nullable();
            $table->integer('DraftYN')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_statutory_details');
    }
};
