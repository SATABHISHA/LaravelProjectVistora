<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CheckinPolicyOnDutyType;

class CheckinPolicyOnDutyTypeApiController extends Controller
{
    // Add
    public function store(Request $request)
    {
        $request->validate([
            'puid' => 'required|string',
            'corp_id' => 'required|string',
            'onduty_type' => 'required|string',
            'onduty_applicability_type' => 'required|string',
            'onduty_limit' => 'required|string'
        ]);

        $record = CheckinPolicyOnDutyType::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'On Duty Type added successfully.',
            'data' => $record
        ], 201);
    }

    // Delete by puid
    public function destroy($puid)
    {
        $deleted = CheckinPolicyOnDutyType::where('puid', $puid)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'On Duty Type deleted successfully.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'On Duty Type not found.'
            ], 404);
        }
    }

    // Fetch by puid
    public function fetchByPuid($puid)
    {
        $record = CheckinPolicyOnDutyType::where('puid', $puid)->get();

        if ($record->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No On Duty Type found for this puid.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $record
        ]);
    }
}
