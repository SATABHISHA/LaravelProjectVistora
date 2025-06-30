<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeEducation extends Model
{
    use HasFactory;

    protected $table = 'employee_educations';

    protected $fillable = [
        'corp_id', 'empcode', 'Degree', 'Specialization', 'Type',
        'FromYear', 'ToYear', 'University', 'Institute', 'Grade'
    ];
}
