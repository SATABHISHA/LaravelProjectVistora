<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayrollSalaryProcess extends Model
{
    use HasFactory;

    protected $table = 'employee_payroll_salary_process';

    protected $fillable = [
        'corpId',
        'empCode',
        'companyName',
        'year',
        'month',
        'grossList',
        'otherAllowances',
        'otherBenefits',
        'recurringDeduction',
        'status',
        'isShownToEmployeeYn',
    ];
}
