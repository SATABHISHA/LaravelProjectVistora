<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;

class CurrencyApiController extends Controller
{
    // Add Currency
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:currencies,name'
        ]);

        $currency = Currency::create(['name' => $request->name]);
        return response()->json(['message' => 'Currency added', 'currency' => $currency], 201);
    }

    // Get All Currencies
    public function index()
    {
        return response()->json(Currency::all());
    }

    // Delete Currency by ID or Name
    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer|exists:currencies,id',
            'name' => 'nullable|string|exists:currencies,name'
        ]);

        if ($request->id) {
            $currency = Currency::find($request->id);
        } elseif ($request->name) {
            $currency = Currency::where('name', $request->name)->first();
        } else {
            return response()->json(['message' => 'Provide id or name'], 400);
        }

        if (!$currency) {
            return response()->json(['message' => 'Currency not found'], 404);
        }

        $currency->delete();
        return response()->json(['message' => 'Currency deleted']);
    }
}
