<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\State;
use App\Models\City;

class LocationApiController extends Controller
{
    // Add Country
    public function addCountry(Request $request)
    {
        $request->validate([
            'country_name' => 'required|string',
            'corp_id' => 'required|string'
        ]);

        if (Country::where('country_name', $request->country_name)->where('corp_id', $request->corp_id)->exists()) {
            return response()->json(['message' => 'Duplicate entry for country'], 409);
        }

        $country = Country::create([
            'country_name' => $request->country_name,
            'corp_id' => $request->corp_id
        ]);

        return response()->json(['message' => 'Country added', 'country' => $country], 201);
    }

    // Delete Country by country_id and corp_id
    public function deleteCountry($country_id, $corp_id)
    {
        $country = Country::where('id', $country_id)->where('corp_id', $corp_id)->first();
        if (!$country) return response()->json(['message' => 'Country not found'], 404);
        $country->delete();
        return response()->json(['message' => 'Country deleted']);
    }

    // Add State
    public function addState(Request $request)
    {
        $request->validate([
            'state_name' => 'required|string',
            'country_id' => 'required|integer',
            'corp_id' => 'required|string'
        ]);

        if (State::where('state_name', $request->state_name)
            ->where('country_id', $request->country_id)
            ->where('corp_id', $request->corp_id)
            ->exists()) {
            return response()->json(['message' => 'Duplicate entry for state'], 409);
        }

        $state = State::create([
            'state_name' => $request->state_name,
            'country_id' => $request->country_id,
            'corp_id' => $request->corp_id
        ]);

        return response()->json(['message' => 'State added', 'state' => $state], 201);
    }

    // Delete State by state_id and corp_id
    public function deleteState($state_id, $corp_id)
    {
        $state = State::where('id', $state_id)->where('corp_id', $corp_id)->first();
        if (!$state) return response()->json(['message' => 'State not found'], 404);
        $state->delete();
        return response()->json(['message' => 'State deleted']);
    }

    // Add City
    public function addCity(Request $request)
    {
        $request->validate([
            'city_name' => 'required|string',
            'country_id' => 'required|integer',
            'state_id' => 'required|integer',
            'corp_id' => 'required|string'
        ]);

        if (City::where('city_name', $request->city_name)
            ->where('country_id', $request->country_id)
            ->where('state_id', $request->state_id)
            ->where('corp_id', $request->corp_id)
            ->exists()) {
            return response()->json(['message' => 'Duplicate entry for city'], 409);
        }

        $city = City::create([
            'city_name' => $request->city_name,
            'country_id' => $request->country_id,
            'state_id' => $request->state_id,
            'corp_id' => $request->corp_id
        ]);

        return response()->json(['message' => 'City added', 'city' => $city], 201);
    }

    // Delete City by city_id and corp_id
    public function deleteCity($city_id, $corp_id)
    {
        $city = City::where('id', $city_id)->where('corp_id', $corp_id)->first();
        if (!$city) return response()->json(['message' => 'City not found'], 404);
        $city->delete();
        return response()->json(['message' => 'City deleted']);
    }

    // Get All Countries (optionally by corp_id)
    public function getAllCountries(Request $request)
    {
        $corp_id = $request->query('corp_id');
        $countries = $corp_id
            ? Country::where('corp_id', $corp_id)->get()
            : Country::all();
        return response()->json(['data' => $countries]);
    }

    // Get States by country_id and corp_id
    public function getStates(Request $request)
    {
        $country_id = $request->query('country_id');
        $corp_id = $request->query('corp_id');
        $states = State::where('country_id', $country_id)
            ->where('corp_id', $corp_id)
            ->get();
        return response()->json(['data' => $states]);
    }

    // Get Cities by country_id, state_id, corp_id
    public function getCities(Request $request)
    {
        $country_id = $request->query('country_id');
        $state_id = $request->query('state_id');
        $corp_id = $request->query('corp_id');
        $cities = City::where('country_id', $country_id)
            ->where('state_id', $state_id)
            ->where('corp_id', $corp_id)
            ->get();
        return response()->json(['data' => $cities]);
    }
}
