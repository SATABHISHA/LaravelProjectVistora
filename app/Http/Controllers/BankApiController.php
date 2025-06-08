<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;

class BankApiController extends Controller
{
    // Add bank
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'bank_name' => 'required|string',
        ]);

        // Prevent duplicate bank_name for the same corp_id (case-insensitive)
        $exists = \App\Models\Bank::where('corp_id', $request->corp_id)
            ->whereRaw('LOWER(bank_name) = ?', [strtolower($request->bank_name)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Bank name already exists for this corp_id'], 409);
        }

        $bank = \App\Models\Bank::create($request->all());

        return response()->json(['message' => 'Bank added successfully', 'bank' => $bank], 201);
    }

    // Delete bank by corp_id and id
    public function destroy($corp_id, $id)
    {
        $bank = Bank::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$bank) {
            return response()->json(['message' => 'Bank not found'], 404);
        }

        $bank->delete();

        return response()->json(['message' => 'Bank deleted successfully']);
    }

    // Fetch all banks by corp_id
    public function getByCorpId($corp_id)
    {
        $banks = \App\Models\Bank::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $banks]);
    }
}
