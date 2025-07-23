<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckinPolicy extends Model
{
    use HasFactory;

    protected $table = 'checkin_policy';

    protected $fillable = [
        'puid', 'corp_id', 'policy_name', 'web_checkin_yn', 'punches_yn', 'punches_no',
        'restrict_emp_marking_attndc_yn', 'punch_start_time', 'punch_end_time', 'IP_validation_yn',
        'from_ip', 'to_ip', 'web_chckin_rqst_frm_whatsapp_yn', 'web_chckin_rqst_frm_teams_yn',
        'mobile_chckin_yn', 'photo_attdnc_yn', 'no_of_photos', 'location_attdnc_yn', 'adv_location_tracking_yn',
        'specific_period_wrk_location_approval_yn', 'location_approval_days_limit', 'max_punches_allowed_yn',
        'punches_no_allowed', 'restrict_emp_attndc_yn', 'attdnc_regularization_yn', 'emp_bck_dated_attdnc_regularization_yn',
        'emp_bck_dated_attdnc_regularization_days', 'mngr_bck_dated_regularization_yn', 'mngr_bck_dated_regularization_days',
        'hr_bck_dated_attdnc_regularization_yn', 'hr_bck_dated_attdnc_regularization_days', 'attdnc_regularization_limit_type',
        'attdnc_regularization_total_limit', 'ar_aftr_attdnc_process_yn', 'future_dtd_attdnc_regularization_yn',
        'atleast_one_punch_attdnc_regularization_yn', 'for_ar_attachment_yn', 'whatsapp_ar_rqst_yn', 'teams_ar_rqst_yn',
        'ar_week_off_emp_restricted_yn', 'ar_holidays_emp_restricted_yn', 'on_duty_yn', 'emp_bck_dtd_onduty_rqst_yn',
        'emp_bck_dtd_onduty_rqst_days', 'mngr_bck_dtd_onduty_rqst_yn', 'mngr_bck_dtd_onduty_rqst_days',
        'hr_bck_dtd_onduty_rqst_yn', 'hr_bck_dtd_onduty_rqst_days', 'configure_overall_onduty_limit_yn',
        'project_log_time_yn', 'raise_onduty_aftr_attdnc_process_yn', 'future_dtd_onduty_yn',
        'restrict_manager_onduty_beyond_limit_yn', 'attchmnt_for_od_yn', 'whatsapp_od_rqst_yn', 'teams_od_rqst_yn',
        'onduty_week_off_emp_restricted_yn', 'onduty_holidays_emp_restricted_yn', 'applicability_type',
        'applicability_for', 'advnc_applicability_type', 'advnc_applicability_for', 'from_days', 'to_days'
    ];
}
