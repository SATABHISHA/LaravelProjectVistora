<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeWorkExperience;
use Carbon\Carbon;

class EmployeeWorkExperienceApiController extends Controller
{
    // Add Work Experience
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'empcode' => 'required|string',
            'CompanyName' => 'required|string',
            'Designation' => 'required|string',
            'FromDate' => 'required|string',
            'ToDate' => 'required|string',
        ]);
        $exp = EmployeeWorkExperience::create($request->all());
        return response()->json(['message' => 'Work experience added', 'data' => $exp], 201);
    }

    // Update Work Experience by corp_id, empcode, id
    public function update(Request $request, $corp_id, $empcode, $id)
    {
        $exp = EmployeeWorkExperience::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();
        if (!$exp) {
            return response()->json(['message' => 'Work experience not found'], 404);
        }
        $exp->update($request->all());
        return response()->json(['message' => 'Work experience updated', 'data' => $exp]);
    }

    // Delete Work Experience by corp_id, empcode, id
    public function destroy($corp_id, $empcode, $id)
    {
        $exp = EmployeeWorkExperience::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();
        if (!$exp) {
            return response()->json(['message' => 'Work experience not found'], 404);
        }
        $exp->delete();
        return response()->json(['message' => 'Work experience deleted']);
    }

    // Fetch Work Experience by corp_id, empcode and return total months
    public function show($corp_id, $empcode)
    {
        $experiences = EmployeeWorkExperience::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->get();

        if ($experiences->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No work experience found',
                'data' => []
            ]);
        }

        // Add total_months to each experience
        $data = $experiences->map(function ($exp) {
            try {
                $from = Carbon::parse($exp->FromDate);
                $to = Carbon::parse($exp->ToDate);
                $exp->total_months = $from->diffInMonths($to);
            } catch (\Exception $e) {
                $exp->total_months = 0;
            }
            return $exp;
        });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
}
