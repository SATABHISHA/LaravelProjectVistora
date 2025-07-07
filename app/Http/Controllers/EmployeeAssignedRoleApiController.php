<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeAssignedRole;

class EmployeeAssignedRoleApiController extends Controller
{
    // Add Employee Assigned Roles (bulk insert)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'role_name' => 'required|string',
            'employee_name' => 'required|string',
            'empcode' => 'nullable|string',
            'company_names' => 'required|string',
            'business_unit' => 'required|string',
            'department' => 'required|string',
            'sub_department_names' => 'required|string',
            'designation' => 'required|string',
            'grade' => 'required|string',
            'level' => 'required|string',
            'region' => 'required|string',
            'branch' => 'required|string',
            'sub_branch' => 'required|string',
        ]);

        $employee_names = array_map('trim', explode(',', $request->employee_name));
        $empcodes = $request->empcode ? array_map('trim', explode(',', $request->empcode)) : [];
        $fields = [
            'company_names', 'business_unit', 'department', 'sub_department_names',
            'designation', 'grade', 'level', 'region', 'branch', 'sub_branch'
        ];
        $otherData = [];
        foreach ($fields as $field) {
            $otherData[$field] = $request->$field;
        }

        $inserted = [];
        foreach ($employee_names as $i => $ename) {
            $empcode = $empcodes[$i] ?? null;

            // Allow same empcode+corp_id for different role_name, but not for same role_name
            if (
                $empcode &&
                EmployeeAssignedRole::where('corp_id', $request->corp_id)
                    ->where('empcode', $empcode)
                    ->where('role_name', $request->role_name)
                    ->exists()
            ) {
                // Skip this entry and continue with the next
                continue;
            }

            $data = [
                'corp_id' => $request->corp_id,
                'role_name' => $request->role_name,
                'employee_name' => $ename,
                'empcode' => $empcode,
            ];
            foreach ($fields as $field) {
                $data[$field] = $otherData[$field];
            }
            $inserted[] = EmployeeAssignedRole::create($data);
        }

        return response()->json([
            'message' => 'Employee roles assigned (duplicates skipped for empcode+corp_id+role_name)',
            'data' => $inserted
        ], 201);
    }

    // Fetch by corp_id, group by role_name, and parse comma fields to arrays
    public function getByCorpId($corp_id)
    {
        $roles = EmployeeAssignedRole::where('corp_id', $corp_id)->get()->groupBy('role_name');
        $result = [];

        foreach ($roles as $role_name => $rows) {
            $roleData = [];
            foreach ($rows as $row) {
                $roleData[] = [
                    'employee_name' => $row->employee_name,
                    'empcode' => $row->empcode,
                    'company_names' => explode(',', $row->company_names),
                    'business_unit' => explode(',', $row->business_unit),
                    'department' => explode(',', $row->department),
                    'sub_department_names' => explode(',', $row->sub_department_names),
                    'designation' => explode(',', $row->designation),
                    'grade' => explode(',', $row->grade),
                    'level' => explode(',', $row->level),
                    'region' => explode(',', $row->region),
                    'branch' => explode(',', $row->branch),
                    'sub_branch' => explode(',', $row->sub_branch),
                ];
            }
            $result[] = [
                'role_name' => $role_name,
                'data' => $roleData
            ];
        }

        return response()->json(['data' => $result]);
    }

    // Update EmployeeAssignedRole by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $role = EmployeeAssignedRole::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$role) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $request->validate([
            'role_name' => 'sometimes|string',
            'employee_name' => 'sometimes|string',
            'empcode' => 'nullable|string',
            'company_names' => 'sometimes|string',
            'business_unit' => 'sometimes|string',
            'department' => 'sometimes|string',
            'sub_department_names' => 'sometimes|string',
            'designation' => 'sometimes|string',
            'grade' => 'sometimes|string',
            'level' => 'sometimes|string',
            'region' => 'sometimes|string',
            'branch' => 'sometimes|string',
            'sub_branch' => 'sometimes|string',
        ]);

        $role->update($request->all());

        return response()->json(['message' => 'Record updated', 'data' => $role]);
    }

    // Delete EmployeeAssignedRole by corp_id and id
    public function destroy($corp_id, $id)
    {
        $role = EmployeeAssignedRole::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$role) {
            return response()->json(['message' => 'Record not found'], 404);
        }
        $role->delete();
        return response()->json(['message' => 'Record deleted']);
    }
}
