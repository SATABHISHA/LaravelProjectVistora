<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAssignedRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id',
        'role_name',
        'employee_name',
        'empcode',
        'company_names',
        'business_unit',
        'department',
        'sub_department_names',
        'designation',
        'grade',
        'level',
        'region',
        'branch',
        'sub_branch',
    ];
}
