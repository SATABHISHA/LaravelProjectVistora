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
     * Fetch leave requests by status for a specific admin/supervisor.
     * The status is passed as a dynamic URL parameter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $status The desired status (e.g., 'Pending', 'Approved', 'Rejected').
     * @param  string  $corp_id
     * @param  string  $empcode The employee code of the user making the request.
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchRequestsByStatus(Request $request, $status, $corp_id, $empcode)
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
                'message' => 'Access Denied. You do not have permission to view these requests.'
            ], 403);
        }

        try {
            // Sanitize the status input (e.g., convert 'pending' to 'Pending')
            $formattedStatus = ucfirst(strtolower($status));

            // Fetch paginated leave requests with the dynamic status for the given corp_id.
            $perPage = $request->input('per_page', 15);

            $leaveRequests = LeaveRequest::where('corp_id', $corp_id)
                ->where('status', $formattedStatus) // Use the dynamic status from the URL
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($leaveRequests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => "No {$formattedStatus} leave requests found for this corporate ID.",
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => "{$formattedStatus} leave requests retrieved successfully.",
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
     * Update the status of a specific leave request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id The ID of the leave request to update.
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'corp_id' => 'required|string', // corp_id of the admin/supervisor
            'empcode' => 'required|string', // empcode of the admin/supervisor
            'status' => 'required|string|in:Approved,Rejected,Returned',
            'reject_reason' => 'required_if:status,Rejected|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $adminCorpId = $request->input('corp_id');
        $adminEmpcode = $request->input('empcode');

        // 1. Authorization Check: Verify if the user making the request is an active admin or supervisor.
        $isAuthorized = DB::table('userlogin')
            ->where('corp_id', $adminCorpId)
            ->where('empcode', $adminEmpcode)
            ->where('active_yn', 1)
            ->where(function ($query) {
                $query->where('admin_yn', 1)->orWhere('supervisor_yn', 1);
            })
            ->exists();

        if (!$isAuthorized) {
            return response()->json(['status' => false, 'message' => 'Access Denied. You do not have permission to perform this action.'], 403);
        }

        try {
            // 2. Find the leave request to be updated.
            $leaveRequest = LeaveRequest::find($id);
            if (!$leaveRequest) {
                return response()->json(['status' => false, 'message' => 'Leave request not found.'], 404);
            }

            // 3. Construct the 'approved_reject_return_by' string for the admin/supervisor.
            $adminEmployment = DB::table('employment_details')->where('corp_id', $adminCorpId)->where('EmpCode', $adminEmpcode)->first();
            $adminDetails = DB::table('employee_details')->where('corp_id', $adminCorpId)->where('EmpCode', $adminEmpcode)->first();

            if (!$adminEmployment || !$adminDetails) {
                return response()->json(['status' => false, 'message' => 'Approver details not found.'], 404);
            }

            $adminNameParts = array_filter([$adminDetails->FirstName, $adminDetails->MiddleName, $adminDetails->LastName]);
            $adminFullName = implode(' ', $adminNameParts);
            $approvedByString = "{$adminEmployment->Designation} - {$adminFullName}";

            // 4. Update the leave request fields.
            $leaveRequest->status = $request->input('status');
            $leaveRequest->approved_reject_return_by = $approvedByString;
            $leaveRequest->reject_reason = $request->input('status') === 'Rejected' ? $request->input('reject_reason') : null;
            $leaveRequest->save();

            return response()->json([
                'status' => true,
                'message' => "Leave request has been successfully {$leaveRequest->status}.",
                'data' => $leaveRequest
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'An error occurred while updating the request.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch all leave requests for a specific employee.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $corp_id
     * @param  string  $company_name
     * @param  string  $empcode
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchForEmployee(Request $request, $corp_id, $company_name, $empcode)
    {
        try {
            $perPage = $request->input('per_page', 15);

            $leaveRequests = LeaveRequest::where('corp_id', $corp_id)
                ->where('company_name', $company_name)
                ->where('empcode', $empcode)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($leaveRequests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No leave requests found for this employee.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Employee leave requests retrieved successfully.',
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
