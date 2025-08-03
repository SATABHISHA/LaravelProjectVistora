<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'corpId',
        'puid',
        'componentName',
        'componentType',
        'payType',
        'paymentInterval',
        'isPartOfCtcYn',
        'isPartOfGrossYn',
        'isIncludedInSalaryYn',
        'paymentNature',
        'rqstVariableTypeEmpYn',
        'rqstVariableTypeManagerYn',
        'rqstVariableTypeHrYn',
        'isVariableAttachmentRequiredYn',
        'isProratedByPaidDaysYn',
        'arrearApplicableYn',
        'processInJoiningMonthYn',
        'processInSettlementMonthYn',
        'pfYn',
        'ptYn',
        'employeeStateInsuranceYn',
        'isIncludedForEsiCheck',
        'bonusYn',
        'isIncludedForBonusCheck',
        'labourWelfareFundYn',
        'gratuityYn',
        'leaveEncashmentYn',
        'roundOffConfiguration',
        'isShownOnSalarySlip',
        'isShownOnSalaryRegister',
        'isShownRateOnSalarySlip',
        'salaryRegisterSortOrder',
        'taxConfigurationType',
        'nonTaxableLimit',
        'taxComputationMethodType',
        'mapIncomeToSectionType',
        'isIncludeInFBPBasket',
    ];
}
