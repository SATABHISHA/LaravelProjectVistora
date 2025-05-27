<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;

class DepartmentApiController extends Controller
{
    // Add department
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string|exists:company_details,corp_id',
            'department_name' => 'required|string',
            'active_yn' => 'boolean'
        ]);

        $department = Department::create($request->all());

        return response()->json(['message' => 'Department added successfully', 'department' => $department], 201);
    }

    // Update department
    public function update(Request $request, $department_id)
    {
        $department = Department::find($department_id);

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $request->validate([
            'department_name' => 'sometimes|required|string',
            'active_yn' => 'boolean'
        ]);

        $department->update($request->all());

        return response()->json(['message' => 'Department updated successfully', 'department' => $department]);
    }

    // Delete department
    public function destroy($department_id)
    {
        $department = Department::find($department_id);

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully']);
    }
}
