<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobFunction;

class JobFunctionApiController extends Controller
{
    // Add job function
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'jobfunction_name' => 'required|string',
        ]);

        // Prevent duplicate jobfunction_name for the same corp_id (case-insensitive)
        $exists = JobFunction::where('corp_id', $request->corp_id)
            ->whereRaw('LOWER(jobfunction_name) = ?', [strtolower($request->jobfunction_name)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Job function already exists for this corp_id'], 409);
        }

        $jobFunction = JobFunction::create($request->all());

        return response()->json(['message' => 'Job function added successfully', 'job_function' => $jobFunction], 201);
    }

    // Delete job function by corp_id and id
    public function destroy($corp_id, $id)
    {
        $jobFunction = JobFunction::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$jobFunction) {
            return response()->json(['message' => 'Job function not found'], 404);
        }

        $jobFunction->delete();

        return response()->json(['message' => 'Job function deleted successfully']);
    }

    // Fetch all job functions by corp_id
    public function getByCorpId($corp_id)
    {
        $jobFunctions = JobFunction::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $jobFunctions]);
    }
}
