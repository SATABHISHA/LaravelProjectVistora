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
            'Type' => 'required|string',
            'FromYear' => 'required|string',
            'ToYear' => 'required|string',
            // Nullable fields
            'Specialization' => 'nullable|string',
            'University' => 'nullable|string',
            'Institute' => 'nullable|string',
            'Grade' => 'nullable|string',
        ]);

        // Process the request data
        $data = $request->all();
        
        // Replace empty nullable fields with "N/A"
        $data['Specialization'] = $data['Specialization'] ?: 'N/A';
        $data['University'] = $data['University'] ?: 'N/A';
        $data['Institute'] = $data['Institute'] ?: 'N/A';
        $data['Grade'] = $data['Grade'] ?: 'N/A';
        
        $education = EmployeeEducation::create($data);
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
        
        // Validate input
        $request->validate([
            'corp_id' => 'string',
            'empcode' => 'string',
            'Degree' => 'string',
            'Type' => 'string',
            'FromYear' => 'string',
            'ToYear' => 'string',
            // Nullable fields
            'Specialization' => 'nullable|string',
            'University' => 'nullable|string',
            'Institute' => 'nullable|string',
            'Grade' => 'nullable|string',
        ]);
        
        // Process the request data
        $data = $request->all();
        
        // Replace empty nullable fields with "N/A" if they exist in the request
        if (array_key_exists('Specialization', $data) && empty($data['Specialization'])) {
            $data['Specialization'] = 'N/A';
        }
        
        if (array_key_exists('University', $data) && empty($data['University'])) {
            $data['University'] = 'N/A';
        }
        
        if (array_key_exists('Institute', $data) && empty($data['Institute'])) {
            $data['Institute'] = 'N/A';
        }
        
        if (array_key_exists('Grade', $data) && empty($data['Grade'])) {
            $data['Grade'] = 'N/A';
        }
        
        $education->update($data);
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
