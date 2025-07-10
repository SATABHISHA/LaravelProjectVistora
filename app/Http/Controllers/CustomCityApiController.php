<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomCity;

class CustomCityApiController extends Controller
{
    // Add city
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'city_name' => 'required|string',
        ]);

        $city = CustomCity::create($request->only('corp_id', 'city_name'));

        return response()->json([
            'status' => true,
            'message' => 'City added successfully.',
            'data' => $city
        ], 201);
    }

    // Fetch distinct cities by corp_id
    public function getByCorpId($corp_id)
    {
        $cities = CustomCity::where('corp_id', $corp_id)
            ->distinct()
            ->pluck('city_name')
            ->toArray();

        return response()->json([
            'status' => true,
            'data' => $cities
        ]);
    }

    // Delete by corp_id and city_name
    public function destroyByCorpIdAndCityName($corp_id, $city_name)
    {
        $deleted = CustomCity::where('corp_id', $corp_id)
            ->where('city_name', $city_name)
            ->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'City deleted for this corp_id and city_name.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No city found for this corp_id and city_name.'
            ], 404);
        }
    }
}
