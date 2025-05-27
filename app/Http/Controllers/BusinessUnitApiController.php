<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BusinessUnit;

class BusinessUnitApiController extends Controller
{
    // Add business unit
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string|exists:company_details,corp_id',
            'business_unit_name' => 'required|string',
            'active_yn' => 'boolean'
        ]);

        $unit = BusinessUnit::create($request->all());

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
}
