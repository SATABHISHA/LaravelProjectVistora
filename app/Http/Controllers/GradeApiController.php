<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grade;

class GradeApiController extends Controller
{
    // Insert grade
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'grade' => 'required|string',
        ]);

        $grade = Grade::create($request->all());

        return response()->json(['message' => 'Grade added successfully', 'grade' => $grade], 201);
    }

    // Fetch all grades by corp_id
    public function getByCorpId($corp_id)
    {
        $grades = Grade::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $grades]);
    }

    // Delete grade
    public function destroy($corp_id, $grade_id)
    {
        $grade = Grade::where('corp_id', $corp_id)->where('grade_id', $grade_id)->first();
        if (!$grade) {
            return response()->json(['message' => 'Grade not found'], 404);
        }
        $grade->delete();
        return response()->json(['message' => 'Grade deleted']);
    }
}
