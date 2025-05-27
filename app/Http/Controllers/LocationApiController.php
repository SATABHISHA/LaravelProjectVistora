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
        $request->validate(['country_name' => 'required|unique:countries,country_name']);
        $country = Country::create(['country_name' => $request->country_name]);
        return response()->json(['message' => 'Country added', 'country' => $country], 201);
    }

    // Delete Country
    public function deleteCountry($country_id)
    {
        $country = Country::find($country_id);
        if (!$country) return response()->json(['message' => 'Country not found'], 404);
        $country->delete();
        return response()->json(['message' => 'Country deleted']);
    }

    // Add State
    public function addState(Request $request)
    {
        $request->validate([
            'country_id' => 'required|exists:countries,country_id',
            'state_name' => 'required'
        ]);
        $state = State::create([
            'country_id' => $request->country_id,
            'state_name' => $request->state_name
        ]);
        return response()->json(['message' => 'State added', 'state' => $state], 201);
    }

    // Delete State
    public function deleteState($state_id)
    {
        $state = State::find($state_id);
        if (!$state) return response()->json(['message' => 'State not found'], 404);
        $state->delete();
        return response()->json(['message' => 'State deleted']);
    }

    // Add City
    public function addCity(Request $request)
    {
        $request->validate([
            'country_id' => 'required|exists:countries,country_id',
            'state_id' => 'required|exists:states,state_id',
            'city_name' => 'required'
        ]);
        $city = City::create([
            'country_id' => $request->country_id,
            'state_id' => $request->state_id,
            'city_name' => $request->city_name
        ]);
        return response()->json(['message' => 'City added', 'city' => $city], 201);
    }

    // Delete City
    public function deleteCity($city_id)
    {
        $city = City::find($city_id);
        if (!$city) return response()->json(['message' => 'City not found'], 404);
        $city->delete();
        return response()->json(['message' => 'City deleted']);
    }
}
