<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Relation;

class RelationApiController extends Controller
{
    // Add Relation
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'relation_name' => 'required|string',
        ]);
        $relation = Relation::create($request->all());
        return response()->json(['message' => 'Relation added', 'data' => $relation], 201);
    }

    // Delete Relation by corp_id
    public function destroy($corp_id)
    {
        $deleted = Relation::where('corp_id', $corp_id)->delete();
        if ($deleted) {
            return response()->json(['message' => 'Relations deleted']);
        }
        return response()->json(['message' => 'No relations found for this corp_id'], 404);
    }

    // Fetch Relations by corp_id
    public function getByCorpId($corp_id)
    {
        $relations = Relation::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $relations]);
    }
}
