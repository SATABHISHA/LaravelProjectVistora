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
            'gracetime_early', // <-- Added this field
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
            'adv_settings_visible_in_wrkplan_rqst_yn',
            'define_weekly_off_yn' // <-- Add this line
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
            'gracetime_early', // <-- Added this field
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
            'adv_settings_visible_in_wrkplan_rqst_yn',
            'define_weekly_off_yn' // <-- Add this line
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

    /**
     * Fetch all shift codes by corp_id
     *
     * @param string $corp_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getShiftCodesByCorpId($corp_id)
    {
        try {
            // Validate corp_id parameter
            if (empty($corp_id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Corp ID is required'
                ], 400);
            }

            // Get distinct shift codes for the given corp_id
            $shiftCodes = ShiftPolicy::where('corp_id', $corp_id)
                ->distinct()
                ->pluck('shift_code')
                ->filter() // Remove null/empty values
                ->values(); // Reset array keys

            if ($shiftCodes->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No shift codes found for the given corp_id',
                    'corp_id' => $corp_id,
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Shift codes retrieved successfully',
                'corp_id' => $corp_id,
                'count' => $shiftCodes->count(),
                'data' => $shiftCodes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving shift codes: ' . $e->getMessage(),
                'corp_id' => $corp_id
            ], 500);
        }
    }

    /**
     * Fetch all shift names by shift_code and corp_id (can be multiple)
     *
     * @param string $corp_id
     * @param string $shift_code
     * @return \Illuminate\Http\JsonResponse
     */
    public function getShiftNamesByCodeAndCorpId($corp_id, $shift_code)
    {
        try {
            // Validate parameters
            if (empty($corp_id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Corp ID is required'
                ], 400);
            }

            if (empty($shift_code)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift code is required'
                ], 400);
            }

            // Find all shift policies by corp_id and shift_code
            $shiftPolicies = ShiftPolicy::where('corp_id', $corp_id)
                ->where('shift_code', $shift_code)
                ->select('puid', 'shift_name', 'shift_start_time', 'shift_code', 'corp_id')
                ->get();

            if ($shiftPolicies->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No shift policies found for the given corp_id and shift_code',
                    'corp_id' => $corp_id,
                    'shift_code' => $shift_code,
                    'data' => []
                ], 404);
            }

            // Format the data
            $shiftNames = $shiftPolicies->map(function ($policy) {
                return [
                    'puid' => $policy->puid,
                    'shift_name' => $policy->shift_name,
                    'shift_start_time' => $policy->shift_start_time
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Shift names retrieved successfully',
                'corp_id' => $corp_id,
                'shift_code' => $shift_code,
                'count' => $shiftNames->count(),
                'data' => $shiftNames
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving shift names: ' . $e->getMessage(),
                'corp_id' => $corp_id,
                'shift_code' => $shift_code
            ], 500);
        }
    }
}
