<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckinPolicyOnDutyType extends Model
{
    use HasFactory;

    protected $table = 'checkin_policy_on_duty_types';

    protected $fillable = [
        'puid',
        'corp_id',
        'onduty_type',
        'onduty_applicability_type',
        'onduty_limit'
    ];
}
