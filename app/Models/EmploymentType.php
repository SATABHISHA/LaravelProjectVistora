<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentType extends Model
{
    protected $fillable = ['corp_id', 'emptype'];
    protected $table = 'employment_type';
}
