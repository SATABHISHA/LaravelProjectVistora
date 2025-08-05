<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeStatutoryDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'EmpCode', 'TaxRegime', 'AdhaarPanLinkedYN', 'ProvidentFundYN', 'PFNo', 'UAN',
        'PensionYN', 'PensionNo', 'EmpStateInsuranceYN', 'EmpStateInsNo', 'EmpStateInsDispensaryName',
        'ESISubUnitCode', 'LabourWelfareFundYN', 'PTYN', 'BonusYN', 'GratuityYN', 'GratuityInCtcYN',
        'DateOfJoin', 'VoluntaryPfYN', 'VoluntaryPFAmount', 'VoluntaryPFPercent', 'VoluntaryPFEffectiveDate',
        'EmployerCtbnToNPSYN', 'EmployerAmount', 'EmployerPercentage', 'EmployerPanNumber', 'SalaryMode',
        'SalaryBank', 'ReimbursementMode', 'ReimbursementBank', 'DraftYN'
    ];
}
