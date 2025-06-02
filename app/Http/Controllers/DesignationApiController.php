<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Designation;

class DesignationApiController extends Controller
{
    // Add Designation
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'designation_name' => 'required|string'
        ]);

        $designation = Designation::create($request->all());

        return response()->json(['message' => 'Designation added successfully', 'designation' => $designation], 201);
    }

    // Fetch all designations by corp_id
    public function getByCorpId($corp_id)
    {
        $designations = Designation::where('corp_id', $corp_id)->get();
        return response()->json($designations);
    }

    // Update designation by corp_id and id
    public function update(Request $request, $id, $corp_id)
    {
        $designation = Designation::where('id', $id)->where('corp_id', $corp_id)->first();
        if (!$designation) {
            return response()->json(['message' => 'Designation not found'], 404);
        }

        $request->validate([
            'designation_name' => 'sometimes|required|string'
        ]);

        $designation->update($request->only(['designation_name']));

        return response()->json(['message' => 'Designation updated successfully', 'designation' => $designation]);
    }

    // Delete designation(s) by corp_id
    public function deleteByCorpId($corp_id)
    {
        $deleted = Designation::where('corp_id', $corp_id)->delete();
        if ($deleted) {
            return response()->json(['message' => 'Designation(s) deleted successfully']);
        } else {
            return response()->json(['message' => 'No designation found for this corp_id'], 404);
        }
    }
}
