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
        //$request->validate(['country_name' => 'required|string']);
        //if (Country::where('country_name', $request->country_name)->exists()) {
        //    return response()->json(['message' => 'Country already exists, can\'t enter duplicate data'], 409);
        //}
        $country = Country::create(['country_name' => 'India']);//$request->country_name
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
            'state_name' => 'required|string|unique:states,state_name'
        ]);

        $state = State::create([
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
            'city_name' => 'required|string|unique:cities,city_name'
        ]);

        $city = City::create([
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

    // Get All Countries
    public function getAllCountries()
    {
        $countries = \App\Models\Country::all();
        return response()->json($countries);
    }

    // Get All States
    public function getAllStates()
    {
        $states = \App\Models\State::all();
        return response()->json($states);
    }

    // Get All Cities
    public function getAllCities()
    {
        $cities = \App\Models\City::all();
        return response()->json($cities);
    }

    // Get States By Country
    public function getStatesByCountry(Request $request)
    {
        $countryName = $request->query('country');
        $country = \App\Models\Country::where('name', $countryName)->first();

        if (!$country) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        $states = \App\Models\State::where('country_id', $country->id)->get();
        return response()->json($states);
    }

    // Get Cities By Country And State
    public function getCitiesByCountryAndState(Request $request)
    {
        $countryName = $request->query('country');
        $stateName = $request->query('state');

        $country = \App\Models\Country::where('name', $countryName)->first();
        if (!$country) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        $state = \App\Models\State::where('name', $stateName)
            ->where('country_id', $country->id)
            ->first();
        if (!$state) {
            return response()->json(['error' => 'State not found'], 404);
        }

        $cities = \App\Models\City::where('country_id', $country->id)
            ->where('state_id', $state->id)
            ->get();
        return response()->json($cities);
    }
}
