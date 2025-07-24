<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CheckinPolicy;

class CheckinPolicyApiController extends Controller
{
    // Add (no duplicate policy_name for same corp_id)
    public function store(Request $request)
    {
        $request->validate([
            'puid' => 'required|string',
            'corp_id' => 'required|string',
            'policy_name' => 'required|string',
        ]);

        $exists = CheckinPolicy::where('corp_id', $request->corp_id)
            ->where('policy_name', $request->policy_name)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Duplicate policy name for this corp_id not allowed.'
            ], 409);
        }

        $data = $request->all();

        // Fields that should default to "N/A" if empty
        $fieldsToDefaultNA = [
            'policy_name',
            'applicability_type',
            'applicability_for',
            'advnc_applicability_type',
            'advnc_applicability_for'
        ];

        foreach ($fieldsToDefaultNA as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $data[$field] = 'N/A';
            }
        }

        // All other fields (except timestamps and id) default to 0 if empty
        $fieldsToDefaultZero = [
            'web_checkin_yn', 'punches_yn', 'punches_no', 'restrict_emp_marking_attndc_yn', 'punch_start_time', 'punch_end_time',
            'IP_validation_yn', 'from_ip', 'to_ip', 'web_chckin_rqst_frm_whatsapp_yn', 'web_chckin_rqst_frm_teams_yn',
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
            'restrict_manager_onduty_beyond_limit_yn', 'restrict_hr_onduty_beyond_limit_yn', 'attchmnt_for_od_yn',
            'whatsapp_od_rqst_yn', 'teams_od_rqst_yn', 'onduty_week_off_emp_restricted_yn', 'onduty_holidays_emp_restricted_yn',
            'from_days', 'to_days'
        ];

        foreach ($fieldsToDefaultZero as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $data[$field] = 0;
            }
        }

        $policy = CheckinPolicy::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Checkin policy added successfully.',
            'data' => $policy
        ], 201);
    }

    // Delete by puid
    public function destroy($puid)
    {
        $deleted = CheckinPolicy::where('puid', $puid)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Checkin policy deleted successfully.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Checkin policy not found.'
            ], 404);
        }
    }

    // Update by puid
    public function update(Request $request, $puid)
    {
        $policy = CheckinPolicy::where('puid', $puid)->first();

        if (!$policy) {
            return response()->json([
                'status' => false,
                'message' => 'Checkin policy not found.'
            ], 404);
        }

        $data = $request->all();

        // Fields that should default to "N/A" if empty
        $fieldsToDefaultNA = [
            'policy_name',
            'applicability_type',
            'applicability_for',
            'advnc_applicability_type',
            'advnc_applicability_for'
        ];

        foreach ($fieldsToDefaultNA as $field) {
            if (isset($data[$field]) && ($data[$field] === null || $data[$field] === '')) {
                $data[$field] = 'N/A';
            }
        }

        // All other fields (except timestamps and id) default to 0 if empty
        $fieldsToDefaultZero = [
            'web_checkin_yn', 'punches_yn', 'punches_no', 'restrict_emp_marking_attndc_yn', 'punch_start_time', 'punch_end_time',
            'IP_validation_yn', 'from_ip', 'to_ip', 'web_chckin_rqst_frm_whatsapp_yn', 'web_chckin_rqst_frm_teams_yn',
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
            'restrict_manager_onduty_beyond_limit_yn', 'restrict_hr_onduty_beyond_limit_yn', 'attchmnt_for_od_yn',
            'whatsapp_od_rqst_yn', 'teams_od_rqst_yn', 'onduty_week_off_emp_restricted_yn', 'onduty_holidays_emp_restricted_yn',
            'from_days', 'to_days'
        ];

        foreach ($fieldsToDefaultZero as $field) {
            if (isset($data[$field]) && ($data[$field] === null || $data[$field] === '')) {
                $data[$field] = 0;
            }
        }

        $policy->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Checkin policy updated successfully.',
            'data' => $policy
        ]);
    }

    // Fetch by corp_id
    public function getByCorpId($corp_id)
    {
        $policies = CheckinPolicy::where('corp_id', $corp_id)->get();

        if ($policies->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No checkin policies found for this corp_id.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $policies
        ]);
    }
}
