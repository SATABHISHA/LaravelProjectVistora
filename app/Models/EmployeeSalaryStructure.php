<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'corpId',
        'puid',
        'empCode',
        'companyName',
        'salaryRevisionMonth',
        'arrearWithEffectFrom',
        'payGroup',
        'ctc',
        'ctcYearly',
        'monthlyBasic',
        'leaveEnchashOnGross',
        'performanceBonus',
        'grossList',
        'otherAlowances',
        'otherBenifits',
        'recurringDeductions',
        'aplb',
        'year',
        'increment'
    ];

    // Cast JSON fields to arrays for easier handling
    protected $casts = [
        'grossList' => 'array',
        'otherBenifits' => 'array',
        'recurringDeductions' => 'array'
    ];
}
