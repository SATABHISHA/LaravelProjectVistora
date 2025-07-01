<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmploymentType;

class EmploymentTypeApiController extends Controller
{
    // Add a new employment type
    public function store(Request $request)
    {
        $validated = $request->validate([
            'corp_id' => 'required|string',
            'emptype' => 'required|string',
        ]);
        $emptype = EmploymentType::create($validated);
        return response()->json($emptype, 201);
    }

    // Update by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $emptype = EmploymentType::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $emptype->update($request->only(['emptype']));
        return response()->json($emptype);
    }

    // Delete by corp_id and id
    public function destroy($corp_id, $id)
    {
        $emptype = EmploymentType::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $emptype->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // Fetch all by corp_id
    public function getByCorpId($corp_id)
    {
        $types = EmploymentType::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $types]);
    }
}
