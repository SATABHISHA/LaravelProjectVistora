<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShiftPolicy;

class ShiftPolicyApiController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->all();

        // Fields that should default to "0" if empty
        $fieldsToDefaultZero = [
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

        foreach ($fieldsToDefaultZero as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $data[$field] = 0;
            }
        }

        $shiftPolicy = ShiftPolicy::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Shift policy added successfully.',
            'data' => $shiftPolicy
        ], 201);
    }

    public function update(Request $request, $corp_id, $puid)
    {
        $shiftPolicy = \App\Models\ShiftPolicy::where('corp_id', $corp_id)
            ->where('puid', $puid)
            ->first();

        if (!$shiftPolicy) {
            return response()->json([
                'status' => false,
                'message' => 'Shift policy not found.'
            ], 404);
        }

        // Fields that should default to "0" if empty
        $fieldsToDefaultZero = [
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

        $data = $request->all();
        foreach ($fieldsToDefaultZero as $field) {
            if (isset($data[$field]) && ($data[$field] === null || $data[$field] === '')) {
                $data[$field] = 0;
            }
        }

        $shiftPolicy->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Shift policy updated successfully.',
            'data' => $shiftPolicy
        ]);
    }

    public function getAllByCorpId($corp_id)
    {
        $shiftPolicies = \App\Models\ShiftPolicy::where('corp_id', $corp_id)->get();

        if ($shiftPolicies->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No shift policies found for this corp_id.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $shiftPolicies
        ]);
    }

    public function deleteByPuid($puid)
    {
        $deleted = \App\Models\ShiftPolicy::where('puid', $puid)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Shift policy deleted successfully.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Shift policy not found.'
            ], 404);
        }
    }
}
