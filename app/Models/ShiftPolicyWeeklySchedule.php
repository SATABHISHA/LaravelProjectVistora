<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftPolicyWeeklySchedule extends Model
{
    use HasFactory;

    protected $table = 'shift_policy_weekly_schedule';

    protected $fillable = [
        'corp_id', 'puid', 'week_no', 'day_name', 'time'
    ];
}
