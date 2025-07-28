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
            'corp_id' => 'required|string',
            'company_name' => 'required|string',
            'business_unit_name' => 'required|string',
            'active_yn' => 'boolean',
        ]);

        // Check for duplicate business unit for the same corp_id
        $exists = \App\Models\BusinessUnit::where('corp_id', $request->corp_id)
            ->where('business_unit_name', $request->business_unit_name)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'This business unit already exists for the given corp_id.'
            ], 409);
        }

        $unit = \App\Models\BusinessUnit::create([
            'corp_id' => $request->corp_id,
            'company_name' => $request->company_name,
            'business_unit_name' => $request->business_unit_name,
            'active_yn' => $request->active_yn ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Business unit created successfully',
            'data' => $unit
        ], 201);
    }

    // Update business unit
    public function update(Request $request, $corp_id, $business_unit_id)
    {
        $request->validate([
            'company_name' => 'required|string',
            'business_unit_name' => 'required|string',
            'active_yn' => 'boolean',
        ]);

        $unit = BusinessUnit::where('business_unit_id', $business_unit_id)
            ->where('corp_id', $corp_id)
            ->first();

        if (!$unit) {
            return response()->json([
                'status' => false,
                'message' => 'Business unit not found'
            ], 404);
        }

        $unit->update([
            'company_name' => $request->company_name,
            'business_unit_name' => $request->business_unit_name,
            'active_yn' => $request->active_yn ?? $unit->active_yn,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Business unit updated successfully',
            'data' => $unit
        ]);
    }

    // Delete business unit
    public function destroy($corp_id, $business_unit_id)
    {
        $unit = \App\Models\BusinessUnit::where('business_unit_id', $business_unit_id)
            ->where('corp_id', $corp_id)
            ->first();

        if (!$unit) {
            return response()->json([
                'status' => false,
                'message' => 'Business unit not found'
            ], 404);
        }

        $unit->delete();

        return response()->json([
            'status' => true,
            'message' => 'Business unit deleted successfully'
        ]);
    }

    // Get all business units by corp_id
    public function getAllByCorpId($corp_id)
    {
        // Get unique business units by business_unit_name for the given corp_id
       /* $businessUnits = BusinessUnit::where('corp_id', $corp_id)
            ->select('business_unit_id', 'corp_id', 'company_name', 'business_unit_name', 'active_yn')
            ->groupBy('business_unit_name', 'business_unit_id', 'corp_id', 'company_name', 'active_yn')
            ->get();*/

     $businessUnits = BusinessUnit::where('corp_id', $corp_id)
    ->select('business_unit_id', 'corp_id', 'company_name', 'business_unit_name', 'active_yn')
    ->orderByDesc('business_unit_id')
    ->get()
    ->unique('business_unit_name')
    ->values();
        return response()->json([
            'status' => true,
            'data' => $businessUnits
        ]);
    }
}
