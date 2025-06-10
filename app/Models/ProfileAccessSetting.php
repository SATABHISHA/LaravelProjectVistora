<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileAccessSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id',
        'profile_tab_name',
        'employee_access_yn',
        'manager_access_yn',
        'other_access_yn'
    ];
}
