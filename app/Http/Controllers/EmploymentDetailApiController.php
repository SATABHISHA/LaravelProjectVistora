<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmploymentDetail;

class EmploymentDetailApiController extends Controller
{
    // Insert
    public function store(Request $request)
    {
        $data = $request->all();

        // Check for duplicate EmpCode within the same corp_id
        $exists = EmploymentDetail::where('corp_id', $data['corp_id'])
            ->where('EmpCode', $data['EmpCode'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Duplicate entry: Employment detail with this EmpCode already exists for this corp_id.'
            ], 409);
        }

        $employment = EmploymentDetail::create($data);
        return response()->json(['data' => $employment], 201);
    }

    // Update by corp_id and EmpCode
    public function update(Request $request, $corp_id, $EmpCode)
    {
        $employment = EmploymentDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->firstOrFail();

        $employment->update($request->all());
        return response()->json(['data' => $employment]);
    }

    // Delete by corp_id and EmpCode
    public function destroy($corp_id, $EmpCode)
    {
        $employment = EmploymentDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->firstOrFail();

        $employment->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    // Fetch by corp_id and EmpCode
    public function show($corp_id, $EmpCode)
    {
        $employment = EmploymentDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->first();

        if (!$employment) {
            return response()->json(['message' => 'Employment detail not found.'], 404);
        }

        return response()->json(['data' => $employment]);
    }
}
