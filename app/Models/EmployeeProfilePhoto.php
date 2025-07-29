<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeProfilePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'emp_code',
        'corp_id',
        'photo_url'
    ];
}
