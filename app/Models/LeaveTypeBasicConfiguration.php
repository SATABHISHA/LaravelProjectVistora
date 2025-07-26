<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveTypeBasicConfiguration extends Model
{
    use HasFactory;

    protected $table = 'leave_type_basic_configurations';

    protected $fillable = [
        'puid',
        'corpid',
        'leaveCode',
        'leaveName',
        'leaveCycleStartMonth',
        'leaveCycleEndMonth',
        'leaveTypeTobeCredited',
        'LimitDays',
        'LeaveType',
        'encahsmentAllowedYN',
        'isConfigurationCompletedYN'
    ];
}
