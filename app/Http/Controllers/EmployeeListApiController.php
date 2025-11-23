<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDetail;
use App\Models\EmploymentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeListApiController extends Controller
{
    /**
     * Get employee list with details from employee_details and employment_details
     * Filtered by corp_id and company_name
     * 
     * GET /api/employee-list?corp_id=xxx&company_name=xxx
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id' => 'required|string',
            'company_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employees = DB::table('employee_details as ed')
                ->join('employment_details as emp', function($join) {
                    $join->on('ed.corp_id', '=', 'emp.corp_id')
                         ->on('ed.EmpCode', '=', 'emp.EmpCode');
                })
                ->where('ed.corp_id', $request->corp_id)
                ->where('emp.company_name', $request->company_name)
                ->select(
                    'ed.EmpCode',
                    DB::raw("CONCAT_WS(' ', ed.FirstName, ed.MiddleName, ed.LastName) as FullName"),
                    'ed.WorkEmail',
                    'ed.Mobile',
                    'emp.Designation'
                )
                ->orderBy('ed.EmpCode')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Employees retrieved successfully',
                'corp_id' => $request->corp_id,
                'company_name' => $request->company_name,
                'total_employees' => $employees->count(),
                'data' => $employees
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
