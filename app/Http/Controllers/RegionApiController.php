<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Region;

class RegionApiController extends Controller
{
    // Add region
    public function store(Request $request)
    {
        $data = $request->validate([
            'corp_id' => 'required|string',
            'region' => 'required|string',
        ]);

        // Prevent duplicate region for same corp_id
        $exists = Region::where('corp_id', $data['corp_id'])
            ->where('region', $data['region'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Region already exists for this corp_id.'], 409);
        }

        $region = Region::create($data);
        return response()->json(['data' => $region], 201);
    }

    // Update region by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $region = Region::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $region->update($request->only('region'));
        return response()->json(['data' => $region]);
    }

    // Delete region by corp_id and id
    public function destroy($corp_id, $id)
    {
        $region = Region::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $region->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    // Fetch all regions by corp_id
    public function getByCorpId($corp_id)
    {
        $regions = Region::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $regions]);
    }
}
