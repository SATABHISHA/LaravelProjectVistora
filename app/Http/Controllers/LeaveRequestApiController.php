<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LeaveRequestApiController extends Controller
{
    /**
     * Store a newly created leave request in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id' => 'required|string',
            'company_name' => 'required|string',
            'empcode' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $corpId = $request->input('corp_id');
            $empcode = $request->input('empcode');
            $companyName = $request->input('company_name');

            // 1. Fetch Designation from employment_details
            $employmentDetails = DB::table('employment_details')
                ->where('corp_id', $corpId)
                ->where('EmpCode', $empcode)
                ->where('company_name', $companyName)
                ->first();

            if (!$employmentDetails || !isset($employmentDetails->Designation)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employment details or designation not found for the given employee.'
                ], 404);
            }
            $designation = $employmentDetails->Designation;

            // 2. Fetch Name parts from employee_details
            $employeeDetails = DB::table('employee_details')
                ->where('corp_id', $corpId)
                ->where('EmpCode', $empcode)
                ->first();

            if (!$employeeDetails) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee master details not found.'
                ], 404);
            }

            // 3. Construct the full_name
            $nameParts = array_filter([
                $employeeDetails->FirstName,
                $employeeDetails->MiddleName,
                $employeeDetails->LastName
            ]);
            $employeeName = implode(' ', $nameParts);
            $fullName = "{$empcode} {$designation} - {$employeeName}";

            // 4. Prepare data for insertion
            $leaveData = [
                'puid' => uniqid('LR_', true), // Auto-generated PUID
                'corp_id' => $corpId,
                'company_name' => $companyName,
                'empcode' => $empcode,
                'full_name' => $fullName, // Auto-generated full_name
                'emp_designation' => $designation, // Auto-inserted designation
                'from_date' => Carbon::parse($request->input('from_date'))->format('d/m/Y'), // Format date
                'to_date' => Carbon::parse($request->input('to_date'))->format('d/m/Y'), // Format date
                'reason' => $request->input('reason'),
                'status' => 'Pending', // Default status
            ];

            // 5. Create the leave request
            $leaveRequest = LeaveRequest::create($leaveData);

            return response()->json([
                'status' => true,
                'message' => 'Leave request submitted successfully.',
                'data' => $leaveRequest
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while processing your request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch pending leave requests for a specific admin/supervisor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $corp_id
     * @param  string  $empcode The employee code of the user making the request.
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchPendingForAdmin(Request $request, $corp_id, $empcode)
    {
        // Authorization Check: Verify if the specific user is an active admin or supervisor.
        $isAuthorized = DB::table('userlogin')
            ->where('corp_id', $corp_id)
            ->where('empcode', $empcode) // Check the specific user
            ->where('active_yn', 1)
            ->where(function ($query) {
                $query->where('admin_yn', 1)
                      ->orWhere('supervisor_yn', 1);
            })
            ->exists();

        // If the user is not an authorized admin/supervisor, deny access.
        if (!$isAuthorized) {
            return response()->json([
                'status' => false,
                'message' => 'Access Denied. You do not have permission to view pending requests.'
            ], 403);
        }

        try {
            // Fetch paginated leave requests with status "Pending" for the given corp_id.
            $perPage = $request->input('per_page', 15);

            $leaveRequests = LeaveRequest::where('corp_id', $corp_id)
                ->where('status', 'Pending')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($leaveRequests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No pending leave requests found for this corporate ID.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Pending leave requests retrieved successfully.',
                'data' => $leaveRequests
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching leave requests.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch approved leave requests for a specific admin/supervisor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $corp_id
     * @param  string  $empcode The employee code of the user making the request.
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchApprovedForAdmin(Request $request, $corp_id, $empcode)
    {
        // Authorization Check: Verify if the specific user is an active admin or supervisor.
        $isAuthorized = DB::table('userlogin')
            ->where('corp_id', $corp_id)
            ->where('empcode', $empcode) // Check the specific user
            ->where('active_yn', 1)
            ->where(function ($query) {
                $query->where('admin_yn', 1)
                      ->orWhere('supervisor_yn', 1);
            })
            ->exists();

        // If the user is not an authorized admin/supervisor, deny access.
        if (!$isAuthorized) {
            return response()->json([
                'status' => false,
                'message' => 'Access Denied. You do not have permission to view approved requests.'
            ], 403);
        }

        try {
            // Fetch paginated leave requests with status "Approved" for the given corp_id.
            $perPage = $request->input('per_page', 15);

            $leaveRequests = LeaveRequest::where('corp_id', $corp_id)
                ->where('status', 'Approved') // The only change is here
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($leaveRequests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No approved leave requests found for this corporate ID.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Approved leave requests retrieved successfully.',
                'data' => $leaveRequests
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching leave requests.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
