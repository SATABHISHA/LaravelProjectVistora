<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Level;

class LevelApiController extends Controller
{
    // Add Level
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'level' => 'required|string',
            'activeYN' => 'required|integer',
        ]);

        // Prevent duplicate level for the same corp_id
        if (Level::where('corp_id', $request->corp_id)->where('level', $request->level)->exists()) {
            return response()->json([
                'message' => 'Level already exists for this corp_id, can\'t enter duplicate data'
            ], 409);
        }

        $level = Level::create($request->only(['corp_id', 'level', 'activeYN']));
        return response()->json(['message' => 'Level added', 'level' => $level], 201);
    }

    // Fetch Levels by corp_id
    public function getByCorpId($corp_id)
    {
        $levels = Level::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $levels]);
    }

    // Delete Level by corp_id and id
    public function destroy($corp_id, $id)
    {
        $level = Level::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$level) {
            return response()->json(['message' => 'Level not found'], 404);
        }
        $level->delete();
        return response()->json(['message' => 'Level deleted']);
    }
}
