<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BusinessUnit;

class BusinessUnitApiController extends Controller
{
    // Add business unit
    public function store(Request $request)
    {
        try {
            $request->validate([
                'company_name' => 'required|string',
                'business_unit_name' => 'required|string',
                'active_yn' => 'boolean'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        $unit = BusinessUnit::create([
            'company_name' => $request->company_name,
            'business_unit_name' => $request->business_unit_name,
            'active_yn' => $request->active_yn ?? true
        ]);

        return response()->json(['message' => 'Business unit added successfully', 'unit' => $unit], 201);
    }

    // Update business unit
    public function update(Request $request, $business_unit_id)
    {
        $unit = BusinessUnit::find($business_unit_id);

        if (!$unit) {
            return response()->json(['message' => 'Business unit not found'], 404);
        }

        $request->validate([
            'business_unit_name' => 'sometimes|required|string',
            'active_yn' => 'boolean'
        ]);

        $unit->update($request->all());

        return response()->json(['message' => 'Business unit updated successfully', 'unit' => $unit]);
    }

    // Delete business unit
    public function destroy($business_unit_id)
    {
        $unit = BusinessUnit::find($business_unit_id);

        if (!$unit) {
            return response()->json(['message' => 'Business unit not found'], 404);
        }

        $unit->delete();

        return response()->json(['message' => 'Business unit deleted successfully']);
    }

    // Get all business units (fetch all, ignore corp_id)
    public function getAllByCorpId($corp_id)
    {
        $businessUnits = BusinessUnit::all();
        return response()->json(['data' => $businessUnits]);
    }
}
