<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftPolicy extends Model
{
    use HasFactory;

    protected $table = 'shiftpolicy';

    protected $fillable = [
        'puid',
        'corp_id',
        'shift_code',
        'shift_name',
        'shift_start_time_hrs',
        'shift_start_time_mins',
        'first_half_hrs',
        'first_half_mins',
        'second_half_hrs',
        'second_half_mins',
        'checkin_hrs',
        'checkin_mins',
        'gracetime_late_mins',
        'gracetime_early_mins',
        'absence_halfday_minhrs',
        'absence_halfday_minmins',
        'absence_fullday_minhrs',
        'absence_fullday_minmins',
        'absence_halfday_absent_aftr_hrs',
        'absence_halfday_absent_aftr_mins',
        'absence_fullday_absent_aftr_hrs',
        'absence_fullday_absent_aftr_mins',
        'absence_secondhalf_absent_chckout_before',
        'absence_shiftallowance_yn',
        'absence_restrict_manager_backdate_yn',
        'absence_restrict_hr_backdate_yn',
        'absence_restrict_manager_future',
        'absence_restrict_hr_future',
        'adv_settings_sihft_break_deduction_yn',
        'adv_settings_deduct_time_before_shift_yn',
        'adv_settings_restrict_work_aftr_cutoff_yn',
        'adv_settings_visible_in_wrkplan_rqst_yn'
    ];
}
