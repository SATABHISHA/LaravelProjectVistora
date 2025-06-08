<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Qualification;

class QualificationApiController extends Controller
{
    // Add qualification
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'qualification_name' => 'required|string',
        ]);

        $qualification = Qualification::create($request->all());

        return response()->json(['message' => 'Qualification added successfully', 'qualification' => $qualification], 201);
    }

    // Delete qualification by corp_id and id
    public function destroy($corp_id, $id)
    {
        $qualification = Qualification::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$qualification) {
            return response()->json(['message' => 'Qualification not found'], 404);
        }

        $qualification->delete();

        return response()->json(['message' => 'Qualification deleted successfully']);
    }
}
