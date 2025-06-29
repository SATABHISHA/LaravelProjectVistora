<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'company_name', 'dateOfJoining', 'EmpCode', 'BiometricId', 'BusinessUnit',
        'Department', 'SubDepartment', 'Designation', 'Region', 'Branch', 'SubBranch',
        'EmploymentType', 'EmploymentStatus', 'ConfirmationStatus', 'ReportingManager',
        'FunctionalManager', 'PFNumber', 'UAN', 'EmployeeContributionLimit', 'EmployerContributionLimit',
        'PensionNumber', 'PF', 'Gratuity'
    ];
}
