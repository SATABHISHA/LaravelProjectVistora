<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeEducation;

class EmployeeEducationApiController extends Controller
{
    // Add Education
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'empcode' => 'required|string',
            'Degree' => 'required|string',
            'Specialization' => 'required|string',
            'Type' => 'required|string',
            'FromYear' => 'required|string',
            'ToYear' => 'required|string',
            'University' => 'required|string',
            'Institute' => 'required|string',
            'Grade' => 'required|string',
        ]);
        $education = EmployeeEducation::create($request->all());
        return response()->json(['message' => 'Education added', 'data' => $education], 201);
    }

    // Update Education by corp_id, empcode, id
    public function update(Request $request, $corp_id, $empcode, $id)
    {
        $education = EmployeeEducation::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();
        if (!$education) {
            return response()->json(['message' => 'Education not found'], 404);
        }
        $education->update($request->all());
        return response()->json(['message' => 'Education updated', 'data' => $education]);
    }

    // Delete Education by corp_id, empcode, id
    public function destroy($corp_id, $empcode, $id)
    {
        $education = EmployeeEducation::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();
        if (!$education) {
            return response()->json(['message' => 'Education not found'], 404);
        }
        $education->delete();
        return response()->json(['message' => 'Education deleted']);
    }

    // Fetch all Education by corp_id, empcode
    public function show($corp_id, $empcode)
    {
        $educations = EmployeeEducation::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->get();

        if ($educations->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No education found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $educations
        ]);
    }
}
