<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Industry;

class IndustryApiController extends Controller
{
    // Add Industry
    public function store(Request $request)
    {
        $request->validate(['industry_name' => 'required|string']);
        if (Industry::where('industry_name', $request->industry_name)->exists()) {
            return response()->json(['message' => 'Industry already exists, can\'t enter duplicate data'], 409);
        }
        $industry = Industry::create(['industry_name' => $request->industry_name]);
        return response()->json(['message' => 'Industry added', 'industry' => $industry], 201);
    }

    // Delete Industry
    public function destroy($industry_id)
    {
        $industry = Industry::find($industry_id);
        if (!$industry) return response()->json(['message' => 'Industry not found'], 404);
        $industry->delete();
        return response()->json(['message' => 'Industry deleted']);
    }
}
