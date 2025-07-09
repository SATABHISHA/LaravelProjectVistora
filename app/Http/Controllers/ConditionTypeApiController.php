<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConditionType;

class ConditionTypeApiController extends Controller
{
    // Add Condition Type (no duplicate for same corp_id)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'condition_type' => 'required|string'
        ]);

        if (ConditionType::where('corp_id', $request->corp_id)->where('condition_type', $request->condition_type)->exists()) {
            return response()->json([
                'message' => 'Condition type already exists for this corp_id, can\'t enter duplicate data'
            ], 409);
        }

        $conditionType = ConditionType::create($request->only(['corp_id', 'condition_type']));
        return response()->json(['message' => 'Condition type added', 'condition_type' => $conditionType], 201);
    }

    // Fetch Condition Types by corp_id
    public function getByCorpId($corp_id)
    {
        $types = ConditionType::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $types]);
    }

    // Delete Condition Type by corp_id and id
    public function destroy($corp_id, $id)
    {
        $type = ConditionType::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$type) {
            return response()->json(['message' => 'Condition type not found'], 404);
        }
        $type->delete();
        return response()->json(['message' => 'Condition type deleted']);
    }
}
