<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveTypeFullConfiguration;

class LeaveTypeFullConfigurationApiController extends Controller
{
    // Add
    public function store(Request $request)
    {
        $leaveType = LeaveTypeFullConfiguration::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Leave type full configuration added successfully.',
            'data' => $leaveType
        ], 201);
    }

    // Update by puid
    public function update(Request $request, $puid)
    {
        $leaveType = LeaveTypeFullConfiguration::where('puid', $puid)->first();

        if (!$leaveType) {
            return response()->json([
                'status' => false,
                'message' => 'Leave type full configuration not found.'
            ], 404);
        }

        $leaveType->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Leave type full configuration updated successfully.',
            'data' => $leaveType
        ]);
    }

    // Delete by puid
    public function destroy($puid)
    {
        $deleted = LeaveTypeFullConfiguration::where('puid', $puid)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Leave type full configuration deleted successfully.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Leave type full configuration not found.'
            ], 404);
        }
    }

    // Fetch by corpid
    public function fetchByCorpid($corpid)
    {
        $records = LeaveTypeFullConfiguration::where('corpid', $corpid)->get();

        if ($records->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No leave type full configurations found for this corpid.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $records
        ]);
    }
}
