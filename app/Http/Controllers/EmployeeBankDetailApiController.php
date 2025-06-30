<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeBankDetail;

class EmployeeBankDetailApiController extends Controller
{
    // Add new bank details (no duplicate for same corp_id + empcode)
    public function store(Request $request)
    {
        $corp_id = $request->input('corp_id');
        $empcode = $request->input('empcode');

        $exists = EmployeeBankDetail::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Bank details already exist for this employee.'
            ], 409);
        }

        $bankDetail = EmployeeBankDetail::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Bank details added',
            'data' => $bankDetail
        ], 201);
    }

    // Update bank details by corp_id, empcode
    public function update(Request $request, $corp_id, $empcode)
    {
        $bankDetail = EmployeeBankDetail::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->first();

        if (!$bankDetail) {
            return response()->json(['status' => false, 'message' => 'Bank details not found'], 404);
        }

        $bankDetail->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Bank details updated',
            'data' => $bankDetail
        ]);
    }

    // Delete bank details by corp_id, empcode
    public function destroy($corp_id, $empcode)
    {
        $bankDetail = EmployeeBankDetail::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->first();

        if (!$bankDetail) {
            return response()->json(['status' => false, 'message' => 'Bank details not found'], 404);
        }

        $bankDetail->delete();

        return response()->json(['status' => true, 'message' => 'Bank details deleted']);
    }

    // Fetch all bank details by corp_id, empcode (status true/false)
    public function show($corp_id, $empcode)
    {
        $bankDetails = EmployeeBankDetail::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->get();

        if ($bankDetails->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No bank details found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $bankDetails
        ]);
    }
}
