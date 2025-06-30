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

     // Fetch last EmpCode by corp_id and return incremented EmpCode, or EMP001 if none exists
    public function getNextEmpCode($corp_id)
    {
        $empCodes = EmploymentDetail::where('corp_id', $corp_id)
            ->pluck('EmpCode')
            ->toArray();

        $maxNumber = 0;
        $prefix = 'EMP';

        foreach ($empCodes as $code) {
            if (preg_match('/^([A-Za-z]*)(\d+)$/', $code, $matches)) {
                $prefix = $matches[1] ?: $prefix;
                $number = (int)$matches[2];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        if ($maxNumber === 0) {
            $nextEmpCode = $prefix . '001';
        } else {
            $nextEmpCode = $prefix . str_pad($maxNumber + 1, 3, '0', STR_PAD_LEFT);
        }

        return response()->json(['nextEmpCode' => $nextEmpCode]);
    }
}
