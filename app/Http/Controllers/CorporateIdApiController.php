<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CorporateId;

class CorporateIdApiController extends Controller
{
    // Add a new corporate ID
    public function store(Request $request)
    {
        $request->validate([
            'corp_id_name' => 'required|string',
            'created_date' => 'required|string',
            'active_yn' => 'required|integer',
            'one_time_payment_yn' => 'required|integer',
            'subscription_yn' => 'required|integer',
        ]);

        $corporate = CorporateId::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Corporate ID added successfully',
            'data' => $corporate
        ], 201);
    }

    // Delete by corp_id_name
    public function destroy($corp_id_name)
    {
        $deleted = CorporateId::where('corp_id_name', $corp_id_name)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Corporate ID deleted successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Corporate ID not found'
            ], 404);
        }
    }

    // Get all corporate IDs
    public function getAll()
    {
        $corporates = CorporateId::all();
        return response()->json([
            'status' => true,
            'data' => $corporates
        ]);
    }
}
