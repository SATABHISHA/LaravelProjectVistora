<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeStatutoryDetail;

class EmployeeStatutoryDetailApiController extends Controller
{
    // Insert with duplicate check
    public function store(Request $request)
    {
        $data = $request->all();

        $exists = EmployeeStatutoryDetail::where('corp_id', $data['corp_id'])
            ->where('EmpCode', $data['EmpCode'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Duplicate entry: Statutory details for this EmpCode already exist for this corp_id.'
            ], 409);
        }

        $statutory = EmployeeStatutoryDetail::create($data);
        return response()->json(['data' => $statutory], 201);
    }

    // Update by corp_id, EmpCode, id
    public function update(Request $request, $corp_id, $EmpCode, $id)
    {
        $statutory = EmployeeStatutoryDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->where('id', $id)
            ->firstOrFail();

        $statutory->update($request->all());
        return response()->json(['data' => $statutory]);
    }

    // Delete by corp_id, EmpCode, id
    public function destroy($corp_id, $EmpCode, $id)
    {
        $statutory = EmployeeStatutoryDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->where('id', $id)
            ->firstOrFail();

        $statutory->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

  
    // Fetch all by corp_id, EmpCode
    public function show($corp_id, $EmpCode)
    {
        $statutory = EmployeeStatutoryDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->get();

        if ($statutory->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No statutory details found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $statutory
        ]);
    }
}
