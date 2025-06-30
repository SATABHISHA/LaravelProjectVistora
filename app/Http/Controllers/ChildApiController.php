<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Child;

class ChildApiController extends Controller
{
    // Add Child
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'EmpCode' => 'required|string',
            'ChildName' => 'required|string',
            'ChildDob' => 'required|string',
            'ChildGender' => 'required|string',
        ]);
        $child = Child::create($request->all());
        return response()->json(['message' => 'Child added', 'data' => $child], 201);
    }

    // Update Child by corp_id and EmpCode
    public function update(Request $request, $corp_id, $EmpCode)
    {
        $child = Child::where('corp_id', $corp_id)->where('EmpCode', $EmpCode)->first();
        if (!$child) {
            return response()->json(['message' => 'Child not found'], 404);
        }
        $child->update($request->all());
        return response()->json(['message' => 'Child updated', 'data' => $child]);
    }

    // Delete Child by corp_id and EmpCode
    public function destroy($corp_id, $EmpCode, $id)
    {
        $child = Child::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->where('id', $id)
            ->first();

        if (!$child) {
            return response()->json(['message' => 'Child not found'], 404);
        }
        $child->delete();
        return response()->json(['message' => 'Child deleted']);
    }

    // Fetch Child by corp_id and EmpCode
    public function show($corp_id, $EmpCode)
    {
        $children = Child::where('corp_id', $corp_id)->where('EmpCode', $EmpCode)->get();
        if ($children->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No child found',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => true,
            'data' => $children
        ]);
    }
}
