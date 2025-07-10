<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomCountry;

class CustomCountryApiController extends Controller
{
    // Add country
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'country_name' => 'required|string',
        ]);

        $country = CustomCountry::create($request->only('corp_id', 'country_name'));

        return response()->json([
            'status' => true,
            'message' => 'Country added successfully.',
            'data' => $country
        ], 201);
    }

    // Fetch by corp_id
    public function getByCorpId($corp_id)
    {
        $countries = CustomCountry::where('corp_id', $corp_id)
            ->distinct()
            ->pluck('country_name')
            ->toArray();

        return response()->json([
            'status' => true,
            'data' => $countries
        ]);
    }

    // Delete by corp_id
    public function destroyByCorpId($corp_id)
    {
        $deleted = CustomCountry::where('corp_id', $corp_id)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Countries deleted for this corp_id.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No countries found for this corp_id.'
            ], 404);
        }
    }

    // Delete by corp_id and country_name
    public function destroyByCorpIdAndCountryName($corp_id, $country_name)
    {
        $deleted = CustomCountry::where('corp_id', $corp_id)
            ->where('country_name', $country_name)
            ->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Country deleted for this corp_id and country_name.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No country found for this corp_id and country_name.'
            ], 404);
        }
    }
}
