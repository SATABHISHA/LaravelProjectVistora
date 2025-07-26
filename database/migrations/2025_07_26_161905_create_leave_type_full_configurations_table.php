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
        Schema::create('leave_type_full_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('puid');
            $table->string('corpid', 40);
            $table->string('applicabilityType', 30);
            $table->string('applicabledEmployeeStatus', 30);
            $table->string('applicabledGender', 10);
            $table->string('leaveCreditedType', 30);
            $table->string('allowAdditionalLeaveOnJoin', 10);
            $table->string('roundOffCreditedLeaves', 10);
            $table->string('lapseLeaveYn', 5);
            $table->string('creditLeaveIfResignationPendingYn', 5);
            $table->string('empHalfDayLeaveRqstYN', 5);
            $table->string('empLeaveRqstYN', 5);
            $table->string('empMaxRqstTenureType', 20);
            $table->string('empMaxNoRqstTenure', 5);
            $table->string('empMaxRqstYearType', 20);
            $table->string('empMaxNoRqstYear', 5);
            $table->string('empMaxRqstMonthType', 20);
            $table->string('empMaxNoRqstMonth', 5);
            $table->string('empMinLeaveRequiredToRqstType', 20);
            $table->string('empMinNoLeaveRequiredToRqst', 5);
            $table->string('maxNoContiniousLeaveAllowedType', 20);
            $table->string('maxNoContionousLeaveAllowedNo', 5);
            $table->string('maxNoLeavesYearlyType', 20);
            $table->string('maxNoLeavesYearly', 5);
            $table->string('maxNoLeavesMonthlyType', 20);
            $table->string('maxNoLeavesMonthly', 5);
            $table->string('empBackDtdLeaveYn', 5);
            $table->string('empBackDtdLeaveNo', 5);
            $table->string('minDaysAdvncLeaveRqstType', 20);
            $table->string('minDaysAdvncLeaveRqstNo', 5);
            $table->string('empFutureDtdLeaveYn', 5);
            $table->string('mngrFutureDtdLeaveYn', 5);
            $table->string('hrFutureDtdLeaveYn', 5);
            $table->string('mngrBackDtdLeaveYn', 5);
            $table->string('mngrBackDtdLeaveNo', 5);
            $table->string('hrBackDtdLeaveYn', 5);
            $table->string('hrBackDtdLeaveNo', 5);
            $table->string('leaveApplctnDocRequiredYn', 5);
            $table->string('docRequiredForLeavesNoAbove', 5);
            $table->string('raiseLeaveRqstAftrAttdncProcessedYn', 5);
            $table->string('restrictLeaveRqstOnPendingResignationYn', 5);
            $table->string('restrictLeaveRqstAftrJoiningForSpecicPeriodType', 20);
            $table->string('restrictLeaveRqstAftrJoiningForSpecicPeriodDaysNo', 5);
            $table->integer('excludeAbsentDaysYn');
            $table->string('specificEmpStatusLeaveApplicableYn', 5);
            $table->integer('empStatusProbationYn');
            $table->integer('empStatusConfirmedYn');
            $table->integer('empStatusResignedYn');
            $table->string('leaveRqstApplicableType', 20);
            $table->integer('applicableToGenderYn');
            $table->integer('applicabilityMaleYn');
            $table->integer('applicabilityFemaleYn');
            $table->integer('applicabilityOtherYn');
            $table->integer('enableThisLeaveBirthdayYn');
            $table->string('birthdayLeaveAdvanceDays', 5);
            $table->string('birthdayLeavePostDays', 5);
            $table->integer('weddingAnniversaryLeaveYn');
            $table->string('weedingAnniversaryLeaveAdvanceDays', 5);
            $table->string('weddingAnniversaryLeavePostDays', 5);
            $table->string('advanceLeaveBalanceYn', 5);
            $table->string('advanceBalanceLimit', 5);
            $table->string('blockClubbingWithOtherLeavesYn', 10);
            $table->string('leaveTypes', 30);
            $table->string('leaveDonationYn', 5);
            $table->integer('donateLeaveEmpYn');
            $table->integer('donateLeaveManagerYn');
            $table->integer('donateLeaveHrYn');
            $table->string('maxAnnualLeaveDonation', 5);
            $table->string('maxCarryForwardLeavesType', 20);
            $table->string('maxCarryForwardLeavesBalance', 5);
            $table->string('carryForwardMethod', 20);
            $table->string('carryForwardMethodDays', 5);
            $table->string('nextYearBalanceUsageOfCrntYearYn', 5);
            $table->string('nextYearBalanceUsageOfCrntYearLimit', 5);
            $table->string('backdatedLeaveCancellationAfterCarryForwardYn', 5);
            $table->string('allowBackdatedLeaveAfterCarryForward', 5);
            $table->string('sendCarryForwardAlertYn', 5);
            $table->string('noOfDaysToSendAlertBeforeExpiry', 5);
            $table->string('alertMessage', 100);
            $table->string('includeWeeklyOffAsLeaveYn', 5);
            $table->string('includePostLeaveWeeklyOffYn', 5);
            $table->string('sandwichLeaveYn', 5);
            $table->string('requireAdvanceSubmissionToExcludeWeeklyOffType', 20);
            $table->string('advanceSubmissionDaysBeforeToExcludeWeeklyOff', 5);
            $table->string('holidaysInLeaveCountYn', 5);
            $table->string('includeWeekoffAndHolidayIfSpannedByLeaveYn', 5);
            $table->string('requireAdvanceSubmissionToExcludeWeekoffAndHolidayType', 20);
            $table->string('advanceSubmissionDaysBeforeToExcludeWeekoffAndHoliday', 5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_type_full_configurations');
    }
};
