<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveTypeBasicConfiguration;

class LeaveTypeBasicConfigurationApiController extends Controller
{
    // Add
    public function store(Request $request)
    {
        $request->validate([
            'puid' => 'required|string',
            'corpid' => 'required|string',
            'leaveCode' => 'required|string',
            'leaveName' => 'required|string',
            'leaveCycleStartMonth' => 'required|string',
            'leaveCycleEndMonth' => 'required|string',
            'leaveTypeTobeCredited' => 'required|string',
            'LimitDays' => 'required|string',
            'LeaveType' => 'required|string',
            'encahsmentAllowedYN' => 'required|integer',
            'isConfigurationCompletedYN' => 'required|integer'
        ]);

        // Check for duplicate leaveCode or leaveName for the same corpid
        $exists = LeaveTypeBasicConfiguration::where('corpid', $request->corpid)
            ->where(function($q) use ($request) {
                $q->where('leaveCode', $request->leaveCode)
                  ->orWhere('leaveName', $request->leaveName);
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Duplicate leaveCode or leaveName for this corpid is not allowed.'
            ], 409);
        }

        $leaveType = LeaveTypeBasicConfiguration::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Leave type configuration added successfully.',
            'data' => $leaveType
        ], 201);
    }

    // Update by puid
    public function update(Request $request, $puid)
    {
        $leaveType = LeaveTypeBasicConfiguration::where('puid', $puid)->first();

        if (!$leaveType) {
            return response()->json([
                'status' => false,
                'message' => 'Leave type configuration not found.'
            ], 404);
        }

        // Check for duplicate leaveCode or leaveName for the same corpid, excluding current record
        $exists = LeaveTypeBasicConfiguration::where('corpid', $request->corpid)
            ->where(function($q) use ($request) {
                $q->where('leaveCode', $request->leaveCode)
                  ->orWhere('leaveName', $request->leaveName);
            })
            ->where('puid', '!=', $puid)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Duplicate leaveCode or leaveName for this corpid is not allowed.'
            ], 409);
        }

        $leaveType->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Leave type configuration updated successfully.',
            'data' => $leaveType
        ]);
    }

    // Delete by puid
    public function destroy($puid)
    {
        $deleted = LeaveTypeBasicConfiguration::where('puid', $puid)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Leave type configuration deleted successfully.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Leave type configuration not found.'
            ], 404);
        }
    }

    // Fetch by corpid
    public function fetchByCorpid($corpid)
    {
        $records = LeaveTypeBasicConfiguration::where('corpid', $corpid)->get();

        if ($records->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No leave type configurations found for this corpid.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $records
        ]);
    }
}
