<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaygroupConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'corpId', 'puid', 'GroupName', 'Description', 'Payslip', 'Reimbursement', 'TaxSlip',
        'AppointmentLetter', 'SalaryRevisionLetter', 'ContractLetter', 'IncludedComponents',
        'IsFBPEnabled', 'PfWageComponentsUnderThreshold', 'CtcYearlyYn', 'MonthlyBasicYn',
        'LeaveEncashedOnGrosYn', 'CostToCompanyYn', 'PBYn', 'CTCAllowances', 'ApplicabilityType',
        'ApplicableOn', 'AdvanceApplicabilityType', 'AdvanceApplicableOn', 'FromDays', 'ToDays', 'ActiveYn'
    ];
}
