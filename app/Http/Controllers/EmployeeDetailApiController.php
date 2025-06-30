<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeDetail;

class EmployeeDetailApiController extends Controller
{
    // Insert with duplicate check
    public function store(Request $request)
    {
        $data = $request->all();

        $exists = EmployeeDetail::where('corp_id', $data['corp_id'])
            ->where('EmpCode', $data['EmpCode'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Duplicate entry: Employee with this EmpCode already exists for this corp_id.'
            ], 409);
        }

        $employee = EmployeeDetail::create($data);
        return response()->json(['data' => $employee], 201);
    }

    // Update by corp_id, EmpCode, id
    public function update(Request $request, $corp_id, $EmpCode, $id)
    {
        $employee = EmployeeDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->where('id', $id)
            ->firstOrFail();

        $employee->update($request->all());
        return response()->json(['data' => $employee]);
    }

    // Delete by corp_id, EmpCode, id
    public function destroy($corp_id, $EmpCode, $id)
    {
        $employee = EmployeeDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->where('id', $id)
            ->firstOrFail();

        $employee->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    // Fetch by corp_id, EmpCode
    public function show($corp_id, $EmpCode)
    {
        $employee = EmployeeDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->get();

        if ($employee->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No employee found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $employee
        ]);
    }
}
