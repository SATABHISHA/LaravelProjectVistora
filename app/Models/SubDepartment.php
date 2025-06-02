<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubDepartment extends Model
{
    use HasFactory;

    protected $table = 'subdepartments'; // calling explicitly as it has created sub_departments table

    protected $primaryKey = 'sub_dept_id';

    protected $fillable = [
        'corp_id',
        'department_name',
        'sub_department_name',
        'active_yn'
    ];
}
