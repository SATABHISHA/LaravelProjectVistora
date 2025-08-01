<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'empcode', 'SkillName', 'Proficiency', 'Description'
    ];
}
