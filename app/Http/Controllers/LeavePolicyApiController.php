<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeavePolicy;

class LeavePolicyApiController extends Controller
{
    // Add Leave Policy
    public function store(Request $request)
    {
        $request->validate([
            'corpid' => 'required|string',
            'puid' => 'required|string',
            'policyName' => 'required|string',
            'leaveType' => 'required|string',
            'applicabilityType' => 'required|string',
            'applicabilityOn' => 'required|string',
            'advanceApplicabilityType' => 'nullable|string',
            'advanceApplicabilityOn' => 'nullable|string',
            'fromDays' => 'nullable|string',
            'toDays' => 'nullable|string'
        ]);

        // Prevent duplicate policyName for the same corpid
        $exists = LeavePolicy::where('corpid', $request->corpid)
            ->where('policyName', $request->policyName)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Duplicate policy name for this corpid not allowed.'
            ], 409);
        }

        $data = $request->all();

        // Set defaults for advance applicability fields
        if (!isset($data['advanceApplicabilityType']) || $data['advanceApplicabilityType'] === null || $data['advanceApplicabilityType'] === '') {
            $data['advanceApplicabilityType'] = 'N/A';
        }
        if (!isset($data['advanceApplicabilityOn']) || $data['advanceApplicabilityOn'] === null || $data['advanceApplicabilityOn'] === '') {
            $data['advanceApplicabilityOn'] = 'N/A';
        }

        // Set defaults for fromDays and toDays
        if (!isset($data['fromDays']) || $data['fromDays'] === null || $data['fromDays'] === '') {
            $data['fromDays'] = '0';
        }
        if (!isset($data['toDays']) || $data['toDays'] === null || $data['toDays'] === '') {
            $data['toDays'] = '0';
        }

        $policy = LeavePolicy::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Leave policy added successfully.',
            'data' => $policy
        ], 201);
    }

    // Update Leave Policy by puid
    public function update(Request $request, $puid)
    {
        $policy = LeavePolicy::where('puid', $puid)->first();

        if (!$policy) {
            return response()->json([
                'status' => false,
                'message' => 'Leave policy not found.'
            ], 404);
        }

        $policy->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Leave policy updated successfully.',
            'data' => $policy
        ]);
    }

    // Delete Leave Policy by puid
    public function destroy($puid)
    {
        $deleted = LeavePolicy::where('puid', $puid)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Leave policy deleted successfully.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Leave policy not found.'
            ], 404);
        }
    }

    // Fetch Leave Policies by corpid
    public function getByCorpid($corpid)
    {
        $policies = LeavePolicy::where('corpid', $corpid)->get();

        return response()->json([
            'status' => true,
            'data' => $policies
        ]);
    }
}
