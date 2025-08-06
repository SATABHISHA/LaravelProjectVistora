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
        Schema::create('paygroup_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('corpId');
            $table->string('puid');
            $table->string('GroupName');
            $table->text('Description')->nullable();
            $table->string('Payslip')->nullable();
            $table->string('Reimbursement')->nullable();
            $table->string('TaxSlip')->nullable();
            $table->string('AppointmentLetter')->nullable();
            $table->string('SalaryRevisionLetter')->nullable();
            $table->string('ContractLetter')->nullable();
            $table->text('IncludedComponents')->nullable();
            $table->string('IsFBPEnabled', 50)->nullable();
            $table->string('PfWageComponentsUnderThreshold', 50)->nullable();
            $table->integer('CtcYearlyYn')->nullable();
            $table->integer('MonthlyBasicYn')->nullable();
            $table->integer('LeaveEncashedOnGrosYn')->nullable();
            $table->integer('CostToCompanyYn')->nullable();
            $table->integer('PBYn')->nullable();
            $table->text('CTCAllowances')->nullable();
            $table->string('ApplicabilityType')->nullable();
            $table->string('ApplicableOn')->nullable();
            $table->string('AdvanceApplicabilityType')->nullable();
            $table->string('AdvanceApplicableOn')->nullable();
            $table->string('FromDays')->nullable();
            $table->string('ToDays')->nullable();
            $table->integer('ActiveYn')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paygroup_configurations');
    }
};
