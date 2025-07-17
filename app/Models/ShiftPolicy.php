<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftPolicy extends Model
{
    use HasFactory;

    protected $table = 'shiftpolicy'; // Table name as per migration

    protected $fillable = [
        'puid',
        'corp_id',
        'shift_code',
        'shift_name',
        'shift_start_time',
        'first_half',
        'second_half',
        'checkin',
        'gracetime_late',
        'absence_halfday',
        'absence_fullday',
        'absence_halfday_absent_aftr',
        'absence_fullday_absent_aftr',
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
