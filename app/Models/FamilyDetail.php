<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'EmpCode', 'FatherName', 'FatherDOB', 'MotherName', 'MotherDob',
        'MaritalStatus', 'SpuseName', 'SpouseDob', 'MarriageDate',
        'DependentName', 'DependentRelation', 'DependentDob', 'DependentGender', 'DependentRemarks'
    ];
}
