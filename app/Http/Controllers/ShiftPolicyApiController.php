<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShiftPolicy;

class ShiftPolicyApiController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->all();

        // Prevent duplicate shift_code for the same corp_id
        $exists = ShiftPolicy::where('corp_id', $data['corp_id'] ?? null)
            ->where('shift_code', $data['shift_code'] ?? null)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Shift code already exists for this corp_id.'
            ], 409);
        }

        // Fields that should default to "00.00AM" if empty
        $fieldsToDefaultTime = [
            'shift_start_time',
            'first_half',
            'second_half',
            'checkin',
            'gracetime_late',
            'absence_halfday',
            'absence_fullday',
            'absence_halfday_absent_aftr',
            'absence_fullday_absent_aftr',
            'absence_secondhalf_absent_chckout_before'
        ];

        foreach ($fieldsToDefaultTime as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $data[$field] = '00.00AM';
            }
        }

        // Integer fields that should default to "0" if empty
        $fieldsToDefaultZero = [
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
        $shiftPolicy = ShiftPolicy::where('corp_id', $corp_id)
            ->where('puid', $puid)
            ->first();

        if (!$shiftPolicy) {
            return response()->json([
                'status' => false,
                'message' => 'Shift policy not found.'
            ], 404);
        }

        $data = $request->all();

        // Fields that should default to "00.00AM" if empty
        $fieldsToDefaultTime = [
            'shift_start_time',
            'first_half',
            'second_half',
            'checkin',
            'gracetime_late',
            'absence_halfday',
            'absence_fullday',
            'absence_halfday_absent_aftr',
            'absence_fullday_absent_aftr',
            'absence_secondhalf_absent_chckout_before'
        ];

        foreach ($fieldsToDefaultTime as $field) {
            if (isset($data[$field]) && ($data[$field] === null || $data[$field] === '')) {
                $data[$field] = '00.00AM';
            }
        }

        // Integer fields that should default to "0" if empty
        $fieldsToDefaultZero = [
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
        $shiftPolicies = ShiftPolicy::where('corp_id', $corp_id)->get();

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
        $deleted = ShiftPolicy::where('puid', $puid)->delete();

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
