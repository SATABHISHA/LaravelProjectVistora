<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmploymentStatus;

class EmploymentStatusApiController extends Controller
{
    // Add a new employment status
    public function store(Request $request)
    {
        $validated = $request->validate([
            'corp_id' => 'required|string',
            'emp_status' => 'required|string',
        ]);
        $status = EmploymentStatus::create($validated);
        return response()->json($status, 201);
    }

    // Update by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $status = EmploymentStatus::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $status->update($request->only(['emp_status']));
        return response()->json($status);
    }

    // Delete by corp_id and id
    public function destroy($corp_id, $id)
    {
        $status = EmploymentStatus::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $status->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // Fetch all by corp_id
    public function getByCorpId($corp_id)
    {
        $statuses = EmploymentStatus::where('corp_id', $corp_id)->get();
        return response()->json($statuses);
    }
}
