<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Industry;

class IndustryApiController extends Controller
{
    // Add Industry
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'industry_name' => 'required|string'
        ]);
        if (
            Industry::where('industry_name', $request->industry_name)
                ->where('corp_id', $request->corp_id)
                ->exists()
        ) {
            return response()->json(['message' => 'Industry already exists for this corp_id, can\'t enter duplicate data'], 409);
        }
        $industry = Industry::create([
            'corp_id' => $request->corp_id,
            'industry_name' => $request->industry_name
        ]);
        return response()->json(['message' => 'Industry added', 'industry' => $industry], 201);
    }

    // Delete Industry
    public function destroy($corp_id, $industry_id)
    {
        $industry = Industry::where('corp_id', $corp_id)->where('industry_id', $industry_id)->first();
        if (!$industry) return response()->json(['message' => 'Industry not found'], 404);
        $industry->delete();
        return response()->json(['message' => 'Industry deleted']);
    }

    // Get All Industries by corp_id
    public function getAllByCorpId($corp_id)
    {
        $industries = Industry::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $industries]);
    }
}
