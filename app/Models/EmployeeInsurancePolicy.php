<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeInsurancePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'empcode', 'name', 'relationship', 'dob', 'gender',
        'policy_no', 'insurance_type', 'assured_sum', 'premium',
        'issue_date', 'valid_upto', 'color'
    ];
}
