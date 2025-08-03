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
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->string('corpId');
            $table->string('puid');
            $table->string('componentName');
            $table->string('componentType');
            $table->string('payType');
            $table->string('paymentInterval');
            $table->integer('isPartOfCtcYn');
            $table->integer('isPartOfGrossYn');
            $table->integer('isIncludedInSalaryYn');
            $table->string('paymentNature');
            $table->integer('rqstVariableTypeEmpYn');
            $table->integer('rqstVariableTypeManagerYn');
            $table->integer('rqstVariableTypeHrYn');
            $table->integer('isVariableAttachmentRequiredYn');
            $table->integer('isProratedByPaidDaysYn');
            $table->integer('arrearApplicableYn');
            $table->integer('processInJoiningMonthYn');
            $table->integer('processInSettlementMonthYn');
            $table->integer('pfYn');
            $table->integer('ptYn');
            $table->integer('employeeStateInsuranceYn');
            $table->integer('isIncludedForEsiCheck');
            $table->integer('bonusYn');
            $table->integer('isIncludedForBonusCheck');
            $table->integer('labourWelfareFundYn');
            $table->integer('gratuityYn');
            $table->integer('leaveEncashmentYn');
            $table->string('roundOffConfiguration');
            $table->string('isShownOnSalarySlip', 20);
            $table->string('isShownOnSalaryRegister', 20);
            $table->string('isShownRateOnSalarySlip', 20);
            $table->string('salaryRegisterSortOrder');
            $table->string('taxConfigurationType');
            $table->string('nonTaxableLimit');
            $table->string('taxComputationMethodType', 50);
            $table->string('mapIncomeToSectionType');
            $table->string('isIncludeInFBPBasket', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_components');
    }
};
