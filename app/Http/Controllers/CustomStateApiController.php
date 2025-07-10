<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomState;

class CustomStateApiController extends Controller
{
    // Add state
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'state_name' => 'required|string',
        ]);

        $state = CustomState::create($request->only('corp_id', 'state_name'));

        return response()->json([
            'status' => true,
            'message' => 'State added successfully.',
            'data' => $state
        ], 201);
    }

    // Fetch distinct states by corp_id
    public function getByCorpId($corp_id)
    {
        $states = CustomState::where('corp_id', $corp_id)
            ->distinct()
            ->pluck('state_name')
            ->toArray();

        return response()->json([
            'status' => true,
            'data' => $states
        ]);
    }

    // Delete by corp_id and state_name
    public function destroyByCorpIdAndStateName($corp_id, $state_name)
    {
        $deleted = CustomState::where('corp_id', $corp_id)
            ->where('state_name', $state_name)
            ->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'State deleted for this corp_id and state_name.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No state found for this corp_id and state_name.'
            ], 404);
        }
    }
}
