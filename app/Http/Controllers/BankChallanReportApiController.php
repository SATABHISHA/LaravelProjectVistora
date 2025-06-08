<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BankChallanReport;

class BankChallanReportApiController extends Controller
{
    // Add challan
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'bankchallanreport_name' => 'required|string',
        ]);

        // Prevent duplicate bankchallanreport_name for the same corp_id (case-insensitive)
        $exists = BankChallanReport::where('corp_id', $request->corp_id)
            ->whereRaw('LOWER(bankchallanreport_name) = ?', [strtolower($request->bankchallanreport_name)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Challan name already exists for this corp_id'], 409);
        }

        $challan = BankChallanReport::create($request->all());

        return response()->json(['message' => 'Challan added successfully', 'challan' => $challan], 201);
    }

    // Delete challan by corp_id and id
    public function destroy($corp_id, $id)
    {
        $challan = BankChallanReport::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$challan) {
            return response()->json(['message' => 'Challan not found'], 404);
        }

        $challan->delete();

        return response()->json(['message' => 'Challan deleted successfully']);
    }

    // Fetch all challans by corp_id
    public function getByCorpId($corp_id)
    {
        $challans = BankChallanReport::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $challans]);
    }
}
