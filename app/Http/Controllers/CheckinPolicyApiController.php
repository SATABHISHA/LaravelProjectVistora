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
            // ...add other required fields as needed
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

        $policy = CheckinPolicy::create($request->all());

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

        $policy->update($request->all());

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
