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
            'corp_id' => 'required|string',
            'name' => 'required|string'
        ]);

        // Prevent duplicate currency for the same corp_id
        if (Currency::where('corp_id', $request->corp_id)->where('name', $request->name)->exists()) {
            return response()->json([
                'message' => 'Currency already exists for this corp_id, can\'t enter duplicate data'
            ], 409);
        }

        $currency = Currency::create([
            'corp_id' => $request->corp_id,
            'name' => $request->name
        ]);
        return response()->json(['message' => 'Currency added', 'currency' => $currency], 201);
    }

    // Get All Currencies by corp_id
    public function index($corp_id)
    {
        return response()->json([
            'data' => Currency::where('corp_id', $corp_id)->get()
        ]);
    }

    // Delete Currency by corp_id and ID or Name
    public function destroy(Request $request, $corp_id)
    {
        $request->validate([
            'id' => 'nullable|integer',
            'name' => 'nullable|string'
        ]);

        if ($request->id) {
            $currency = Currency::where('corp_id', $corp_id)->where('id', $request->id)->first();
        } elseif ($request->name) {
            $currency = Currency::where('corp_id', $corp_id)->where('name', $request->name)->first();
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
