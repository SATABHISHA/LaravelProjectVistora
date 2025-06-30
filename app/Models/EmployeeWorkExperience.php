<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeWorkExperience extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'empcode', 'CompanyName', 'Designation', 'FromDate', 'ToDate', 'Description'
    ];
}
