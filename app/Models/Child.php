<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'EmpCode', 'ChildName', 'ChildDob', 'ChildGender',
        'DependentYN', 'GoingSchoolYN', 'StayingHostel'
    ];
}
