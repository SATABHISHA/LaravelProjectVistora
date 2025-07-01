<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentStatus extends Model
{
    protected $fillable = ['corp_id', 'emp_status'];
    protected $table = 'employment_status';
}
