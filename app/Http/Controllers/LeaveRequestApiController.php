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
            'leave_reason_description' => 'nullable|string',
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
                'leave_reason_description' => $request->input('leave_reason_description'),
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

            // Get total count for this specific status and corp_id
            $totalCount = LeaveRequest::where('corp_id', $corp_id)
                ->where('status', $formattedStatus)
                ->count();

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
                    'total_count' => $totalCount,
                    'data' => []
                ], 200);
            }

            // Calculate totalNoDays and nameInitials for each leave request
            $leaveRequests->getCollection()->transform(function ($leaveRequest) {
                try {
                    $fromDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->from_date);
                    $toDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->to_date);
                    $leaveRequest->totalNoDays = $fromDate->diffInDays($toDate) + 1; // +1 to include both start and end dates
                } catch (\Exception $e) {
                    $leaveRequest->totalNoDays = 1; // Default to 1 day if date parsing fails
                }

                // Calculate nameInitials from employee details
                try {
                    $employeeDetails = DB::table('employee_details')
                        ->where('corp_id', $leaveRequest->corp_id)
                        ->where('EmpCode', $leaveRequest->empcode)
                        ->first();
                    
                    if ($employeeDetails) {
                        $firstInitial = $employeeDetails->FirstName ? strtoupper(substr($employeeDetails->FirstName, 0, 1)) : '';
                        $lastInitial = $employeeDetails->LastName ? strtoupper(substr($employeeDetails->LastName, 0, 1)) : '';
                        $leaveRequest->nameInitials = $firstInitial . $lastInitial;
                    } else {
                        $leaveRequest->nameInitials = 'NA';
                    }
                } catch (\Exception $e) {
                    $leaveRequest->nameInitials = 'NA';
                }

                return $leaveRequest;
            });

            return response()->json([
                'status' => true,
                'message' => "{$formattedStatus} leave requests retrieved successfully.",
                'total_count' => $totalCount,
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

            // Calculate totalNoDays and nameInitials for each leave request
            $leaveRequests->getCollection()->transform(function ($leaveRequest) {
                try {
                    $fromDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->from_date);
                    $toDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->to_date);
                    $leaveRequest->totalNoDays = $fromDate->diffInDays($toDate) + 1; // +1 to include both start and end dates
                } catch (\Exception $e) {
                    $leaveRequest->totalNoDays = 1; // Default to 1 day if date parsing fails
                }

                // Calculate nameInitials from employee details
                try {
                    $employeeDetails = DB::table('employee_details')
                        ->where('corp_id', $leaveRequest->corp_id)
                        ->where('EmpCode', $leaveRequest->empcode)
                        ->first();
                    
                    if ($employeeDetails) {
                        $firstInitial = $employeeDetails->FirstName ? strtoupper(substr($employeeDetails->FirstName, 0, 1)) : '';
                        $lastInitial = $employeeDetails->LastName ? strtoupper(substr($employeeDetails->LastName, 0, 1)) : '';
                        $leaveRequest->nameInitials = $firstInitial . $lastInitial;
                    } else {
                        $leaveRequest->nameInitials = 'NA';
                    }
                } catch (\Exception $e) {
                    $leaveRequest->nameInitials = 'NA';
                }

                return $leaveRequest;
            });

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

    /**
     * Fetch people who are on leave today (Approved status)
     * Shows employees whose leave dates include the current date
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $corp_id
     * @param  string  $empcode The employee code of the user making the request (for authorization)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPeopleOnLeaveToday(Request $request, $corp_id, $empcode)
    {
        // Authorization Check: Verify if the specific user is an active admin or supervisor
        $isAuthorized = DB::table('userlogin')
            ->where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('active_yn', 1)
            ->where(function ($query) {
                $query->where('admin_yn', 1)
                      ->orWhere('supervisor_yn', 1);
            })
            ->exists();

        if (!$isAuthorized) {
            return response()->json([
                'status' => false,
                'message' => 'Access Denied. You do not have permission to view this information.'
            ], 403);
        }

        try {
            $today = Carbon::today()->format('d/m/Y');
            $perPage = $request->input('per_page', 15);

            // Query for approved leave requests where today falls between from_date and to_date
            $query = LeaveRequest::where('corp_id', $corp_id)
                ->where('status', 'Approved')
                ->where(function ($dateQuery) use ($today) {
                    // This handles the date range check for d/m/Y format
                    $dateQuery->whereRaw("STR_TO_DATE(from_date, '%d/%m/%Y') <= STR_TO_DATE(?, '%d/%m/%Y')", [$today])
                             ->whereRaw("STR_TO_DATE(to_date, '%d/%m/%Y') >= STR_TO_DATE(?, '%d/%m/%Y')", [$today]);
                });

            // Get total count before pagination
            $totalCount = $query->count();

            // Apply pagination
            $leaveRequests = $query->orderBy('from_date', 'asc')
                ->paginate($perPage);

            if ($leaveRequests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No employees are on approved leave today.',
                    'today_date' => $today,
                    'total_count' => 0,
                    'data' => []
                ], 200);
            }

            // Transform the data to include additional calculated fields
            $leaveRequests->getCollection()->transform(function ($leaveRequest) use ($today) {
                try {
                    // Calculate total leave days
                    $fromDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->from_date);
                    $toDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->to_date);
                    $leaveRequest->totalNoDays = $fromDate->diffInDays($toDate) + 1;

                    // Calculate remaining days from today
                    $todayCarbon = Carbon::createFromFormat('d/m/Y', $today);
                    if ($todayCarbon->lte($toDate)) {
                        $leaveRequest->remainingDays = $todayCarbon->diffInDays($toDate) + 1;
                    } else {
                        $leaveRequest->remainingDays = 0;
                    }

                    // Days completed so far
                    if ($todayCarbon->gte($fromDate)) {
                        $leaveRequest->daysCompleted = $fromDate->diffInDays($todayCarbon) + 1;
                    } else {
                        $leaveRequest->daysCompleted = 0;
                    }

                } catch (\Exception $e) {
                    $leaveRequest->totalNoDays = 1;
                    $leaveRequest->remainingDays = 0;
                    $leaveRequest->daysCompleted = 1;
                }

                // Get employee details for name initials and additional info
                try {
                    $employeeDetails = DB::table('employee_details')
                        ->where('corp_id', $leaveRequest->corp_id)
                        ->where('EmpCode', $leaveRequest->empcode)
                        ->first();
                    
                    if ($employeeDetails) {
                        $firstInitial = $employeeDetails->FirstName ? strtoupper(substr($employeeDetails->FirstName, 0, 1)) : '';
                        $lastInitial = $employeeDetails->LastName ? strtoupper(substr($employeeDetails->LastName, 0, 1)) : '';
                        $leaveRequest->nameInitials = $firstInitial . $lastInitial;
                        
                        // Add full employee name (separate from full_name which includes designation)
                        $nameParts = array_filter([
                            $employeeDetails->FirstName,
                            $employeeDetails->MiddleName,
                            $employeeDetails->LastName
                        ]);
                        $leaveRequest->employee_name = implode(' ', $nameParts);
                    } else {
                        $leaveRequest->nameInitials = 'NA';
                        $leaveRequest->employee_name = 'Unknown';
                    }

                    // Get employment details for department/additional info
                    $employmentDetails = DB::table('employment_details')
                        ->where('corp_id', $leaveRequest->corp_id)
                        ->where('EmpCode', $leaveRequest->empcode)
                        ->first();

                    if ($employmentDetails) {
                        $leaveRequest->department = $employmentDetails->Department ?? 'N/A';
                        $leaveRequest->designation = $employmentDetails->Designation ?? 'N/A';
                    } else {
                        $leaveRequest->department = 'N/A';
                        $leaveRequest->designation = 'N/A';
                    }

                } catch (\Exception $e) {
                    $leaveRequest->nameInitials = 'NA';
                    $leaveRequest->employee_name = 'Unknown';
                    $leaveRequest->department = 'N/A';
                    $leaveRequest->designation = 'N/A';
                }

                // Add leave status details
                $leaveRequest->is_on_leave_today = true; // Since we're filtering for today
                $leaveRequest->leave_type = $leaveRequest->reason ?: 'General Leave';

                return $leaveRequest;
            });

            return response()->json([
                'status' => true,
                'message' => 'People on leave today retrieved successfully.',
                'today_date' => $today,
                'total_count' => $totalCount,
                'data' => $leaveRequests
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching people on leave today.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch people who are on leave today for a specific company
     * Allows non-admin users to see people on leave in their company only
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $corp_id
     * @param  string  $company_name
     * @param  string  $empcode The employee code of the user making the request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPeopleOnLeaveTodayByCompany(Request $request, $corp_id, $company_name, $empcode)
    {
        // Basic authorization: Verify if the user exists and is active
        $userExists = DB::table('userlogin')
            ->where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('active_yn', 1)
            ->exists();

        if (!$userExists) {
            return response()->json([
                'status' => false,
                'message' => 'Access Denied. Invalid user credentials or inactive account.'
            ], 403);
        }

        // Verify the user belongs to the requested company
        $userCompany = DB::table('employment_details')
            ->where('corp_id', $corp_id)
            ->where('EmpCode', $empcode)
            ->where('company_name', $company_name)
            ->exists();

        if (!$userCompany) {
            return response()->json([
                'status' => false,
                'message' => 'Access Denied. You can only view leave information for your own company.'
            ], 403);
        }

        try {
            $today = Carbon::today()->format('d/m/Y');
            $perPage = $request->input('per_page', 15);

            // Query for approved leave requests where today falls between from_date and to_date
            // AND filter by company_name
            $query = LeaveRequest::where('corp_id', $corp_id)
                ->where('company_name', $company_name) // Company filter
                ->where('status', 'Approved')
                ->where(function ($dateQuery) use ($today) {
                    // This handles the date range check for d/m/Y format
                    $dateQuery->whereRaw("STR_TO_DATE(from_date, '%d/%m/%Y') <= STR_TO_DATE(?, '%d/%m/%Y')", [$today])
                             ->whereRaw("STR_TO_DATE(to_date, '%d/%m/%Y') >= STR_TO_DATE(?, '%d/%m/%Y')", [$today]);
                });

            // Get total count before pagination
            $totalCount = $query->count();

            // Apply pagination
            $leaveRequests = $query->orderBy('from_date', 'asc')
                ->paginate($perPage);

            if ($leaveRequests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => "No employees from {$company_name} are on approved leave today.",
                    'today_date' => $today,
                    'company_name' => $company_name,
                    'total_count' => 0,
                    'data' => []
                ], 200);
            }

            // Transform the data to include additional calculated fields
            $leaveRequests->getCollection()->transform(function ($leaveRequest) use ($today) {
                try {
                    // Calculate total leave days
                    $fromDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->from_date);
                    $toDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->to_date);
                    $leaveRequest->totalNoDays = $fromDate->diffInDays($toDate) + 1;

                    // Calculate remaining days from today
                    $todayCarbon = Carbon::createFromFormat('d/m/Y', $today);
                    if ($todayCarbon->lte($toDate)) {
                        $leaveRequest->remainingDays = $todayCarbon->diffInDays($toDate) + 1;
                    } else {
                        $leaveRequest->remainingDays = 0;
                    }

                    // Days completed so far
                    if ($todayCarbon->gte($fromDate)) {
                        $leaveRequest->daysCompleted = $fromDate->diffInDays($todayCarbon) + 1;
                    } else {
                        $leaveRequest->daysCompleted = 0;
                    }

                } catch (\Exception $e) {
                    $leaveRequest->totalNoDays = 1;
                    $leaveRequest->remainingDays = 0;
                    $leaveRequest->daysCompleted = 1;
                }

                // Get employee details for name initials and additional info
                try {
                    $employeeDetails = DB::table('employee_details')
                        ->where('corp_id', $leaveRequest->corp_id)
                        ->where('EmpCode', $leaveRequest->empcode)
                        ->first();
                    
                    if ($employeeDetails) {
                        $firstInitial = $employeeDetails->FirstName ? strtoupper(substr($employeeDetails->FirstName, 0, 1)) : '';
                        $lastInitial = $employeeDetails->LastName ? strtoupper(substr($employeeDetails->LastName, 0, 1)) : '';
                        $leaveRequest->nameInitials = $firstInitial . $lastInitial;
                        
                        // Add full employee name (separate from full_name which includes designation)
                        $nameParts = array_filter([
                            $employeeDetails->FirstName,
                            $employeeDetails->MiddleName,
                            $employeeDetails->LastName
                        ]);
                        $leaveRequest->employee_name = implode(' ', $nameParts);
                    } else {
                        $leaveRequest->nameInitials = 'NA';
                        $leaveRequest->employee_name = 'Unknown';
                    }

                    // Get employment details for department/additional info
                    $employmentDetails = DB::table('employment_details')
                        ->where('corp_id', $leaveRequest->corp_id)
                        ->where('EmpCode', $leaveRequest->empcode)
                        ->first();

                    if ($employmentDetails) {
                        $leaveRequest->department = $employmentDetails->Department ?? 'N/A';
                        $leaveRequest->designation = $employmentDetails->Designation ?? 'N/A';
                    } else {
                        $leaveRequest->department = 'N/A';
                        $leaveRequest->designation = 'N/A';
                    }

                } catch (\Exception $e) {
                    $leaveRequest->nameInitials = 'NA';
                    $leaveRequest->employee_name = 'Unknown';
                    $leaveRequest->department = 'N/A';
                    $leaveRequest->designation = 'N/A';
                }

                // Add leave status details
                $leaveRequest->is_on_leave_today = true;
                $leaveRequest->leave_type = $leaveRequest->reason ?: 'General Leave';
                
                // Privacy: Remove sensitive information for non-admin users
                $leaveRequest->leave_reason_description = 'Private'; // Hide detailed reason
                $leaveRequest->approved_reject_return_by = $leaveRequest->approved_reject_return_by; // Keep approver info

                return $leaveRequest;
            });

            return response()->json([
                'status' => true,
                'message' => "People on leave today from {$company_name} retrieved successfully.",
                'today_date' => $today,
                'company_name' => $company_name,
                'total_count' => $totalCount,
                'data' => $leaveRequests
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching people on leave today.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch employee's own leave requests filtered by status
     * No admin authorization required - employees can only see their own requests
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $status
     * @param  string  $corp_id
     * @param  string  $company_name
     * @param  string  $empcode
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchEmployeeLeavesByStatus(Request $request, $status, $corp_id, $company_name, $empcode)
    {
        try {
            // Sanitize the status input (e.g., convert 'pending' to 'Pending', or 'all' to 'All')
            $formattedStatus = ucfirst(strtolower($status));

            // Check if status is 'All' - return all statuses
            $showAllStatuses = ($formattedStatus === 'All');

            // Validate status if not 'All'
            if (!$showAllStatuses) {
                $validStatuses = ['Pending', 'Approved', 'Rejected', 'Returned'];
                if (!in_array($formattedStatus, $validStatuses)) {
                    return response()->json([
                        'status' => false,
                        'message' => "Invalid status. Valid statuses are: " . implode(', ', $validStatuses) . ", All"
                    ], 400);
                }
            }

            // Build base query
            $query = LeaveRequest::where('corp_id', $corp_id)
                ->where('company_name', $company_name)
                ->where('empcode', $empcode);

            // Add status filter only if not 'All'
            if (!$showAllStatuses) {
                $query->where('status', $formattedStatus);
            }

            // Get total count
            $totalCount = $query->count();

            // Fetch paginated leave requests
            $perPage = $request->input('per_page', 15);

            $leaveRequests = LeaveRequest::where('corp_id', $corp_id)
                ->where('company_name', $company_name)
                ->where('empcode', $empcode);

            // Add status filter only if not 'All'
            if (!$showAllStatuses) {
                $leaveRequests->where('status', $formattedStatus);
            }

            $leaveRequests = $leaveRequests->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($leaveRequests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => $showAllStatuses ? "No leave requests found." : "No {$formattedStatus} leave requests found.",
                    'total_count' => $totalCount,
                    'data' => []
                ], 200);
            }

            // Calculate totalNoDays and nameInitials for each leave request
            $leaveRequests->getCollection()->transform(function ($leaveRequest) {
                try {
                    // Parse dates - handle dd/mm/yyyy format
                    $fromDateStr = $leaveRequest->from_date;
                    $toDateStr = $leaveRequest->to_date;
                    
                    // Try dd/mm/yyyy format first
                    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fromDateStr)) {
                        $fromDate = Carbon::createFromFormat('d/m/Y', $fromDateStr);
                        $toDate = Carbon::createFromFormat('d/m/Y', $toDateStr);
                    } else {
                        // Fallback to standard parsing
                        $fromDate = Carbon::parse($fromDateStr);
                        $toDate = Carbon::parse($toDateStr);
                    }
                    
                    $leaveRequest->totalNoDays = $fromDate->diffInDays($toDate) + 1;
                } catch (\Exception $e) {
                    $leaveRequest->totalNoDays = 1; // Default to 1 day if date parsing fails
                }

                // Calculate nameInitials from employee details
                try {
                    $employeeDetails = DB::table('employee_details')
                        ->where('corp_id', $leaveRequest->corp_id)
                        ->where('EmpCode', $leaveRequest->empcode)
                        ->first();
                    
                    if ($employeeDetails) {
                        $firstInitial = $employeeDetails->FirstName ? strtoupper(substr($employeeDetails->FirstName, 0, 1)) : '';
                        $lastInitial = $employeeDetails->LastName ? strtoupper(substr($employeeDetails->LastName, 0, 1)) : '';
                        $leaveRequest->nameInitials = $firstInitial . $lastInitial;
                    } else {
                        $leaveRequest->nameInitials = 'NA';
                    }
                } catch (\Exception $e) {
                    $leaveRequest->nameInitials = 'NA';
                }

                return $leaveRequest;
            });

            return response()->json([
                'status' => true,
                'message' => $showAllStatuses ? "All leave requests retrieved successfully." : "{$formattedStatus} leave requests retrieved successfully.",
                'corp_id' => $corp_id,
                'company_name' => $company_name,
                'empcode' => $empcode,
                'request_status' => $showAllStatuses ? 'All' : $formattedStatus,
                'total_count' => $totalCount,
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

