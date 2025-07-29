<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use HasFactory;

    protected $table = 'leave_policy';

    protected $fillable = [
        'corpid',
        'puid',
        'policyName',
        'description',
        'leaveType',
        'applicabilityType',
        'applicabilityOn',
        'advanceApplicabilityType',
        'advanceApplicabilityOn',
        'fromDays',
        'toDays'
    ];
}
