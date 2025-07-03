<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeNomineeDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'empcode', 'statutory_type', 'nominee_name', 'relation', 'dob', 'gender',
        'share_percent', 'contact_no', 'addr', 'remarks', 'minor_yn', 'color'
    ];
}
