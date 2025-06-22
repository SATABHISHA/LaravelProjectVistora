<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubDepartment;

class SubDepartmentApiController extends Controller
{
    // Add SubDepartment
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'department_name' => 'required|string',
            'sub_department_name' => 'required|string',
            'active_yn' => 'integer'
        ]);

        $subDept = SubDepartment::create($request->all());

        return response()->json(['message' => 'SubDepartment added successfully', 'subdepartment' => $subDept], 201);
    }

    // Fetch SubDepartments by corp_id
    public function getByCorpId($corp_id)
    {
        $subDepts = SubDepartment::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $subDepts]);
    }

    // Update SubDepartment
    public function update(Request $request, $sub_dept_id)
    {
        $subDept = SubDepartment::find($sub_dept_id);
        if (!$subDept) {
            return response()->json(['message' => 'SubDepartment not found'], 404);
        }

        $request->validate([
            'department_name' => 'sometimes|required|string',
            'sub_department_name' => 'sometimes|required|string',
            'active_yn' => 'sometimes|integer'
        ]);

        $subDept->update($request->only(['department_name', 'sub_department_name', 'active_yn']));

        return response()->json(['message' => 'SubDepartment updated successfully', 'subdepartment' => $subDept]);
    }

    // Delete SubDepartments by corp_id
    public function deleteByCorpId($corp_id)
    {
        $deleted = SubDepartment::where('corp_id', $corp_id)->delete();
        if ($deleted) {
            return response()->json(['message' => 'SubDepartments deleted successfully']);
        } else {
            return response()->json(['message' => 'No SubDepartments found for this corp_id'], 404);
        }
    }

    // Delete SubDepartment by corp_id and sub_dept_id
    public function deleteByCorpIdAndSubDeptId($corp_id, $sub_dept_id)
    {
        $deleted = SubDepartment::where('corp_id', $corp_id)
            ->where('sub_dept_id', $sub_dept_id)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'SubDepartment deleted successfully']);
        } else {
            return response()->json(['message' => 'SubDepartment not found'], 404);
        }
    }
}
