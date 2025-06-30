<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FamilyDetail;

class FamilyDetailApiController extends Controller
{
    // Add Family Detail
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'EmpCode' => 'required|string',
        ]);

        // Check for duplicate entry
        $exists = FamilyDetail::where('corp_id', $request->corp_id)
            ->where('EmpCode', $request->EmpCode)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Family detail already exists for this corp_id and EmpCode'], 409);
        }

        $family = FamilyDetail::create($request->all());
        return response()->json(['message' => 'Family detail added', 'data' => $family], 201);
    }

    // Update Family Detail by corp_id and EmpCode
    public function update(Request $request, $corp_id, $EmpCode)
    {
        $family = FamilyDetail::where('corp_id', $corp_id)->where('EmpCode', $EmpCode)->first();
        if (!$family) {
            return response()->json(['message' => 'Family detail not found'], 404);
        }
        $family->update($request->all());
        return response()->json(['message' => 'Family detail updated', 'data' => $family]);
    }

    // Delete Family Detail by corp_id and EmpCode
    public function destroy($corp_id, $EmpCode)
    {
        $family = FamilyDetail::where('corp_id', $corp_id)->where('EmpCode', $EmpCode)->first();
        if (!$family) {
            return response()->json(['message' => 'Family detail not found'], 404);
        }
        $family->delete();
        return response()->json(['message' => 'Family detail deleted']);
    }

    // Fetch Family Detail by corp_id and EmpCode
    public function show($corp_id, $EmpCode)
    {
        $family = FamilyDetail::where('corp_id', $corp_id)->where('EmpCode', $EmpCode)->get();
        return response()->json(['data' => $family]);
    }
}
