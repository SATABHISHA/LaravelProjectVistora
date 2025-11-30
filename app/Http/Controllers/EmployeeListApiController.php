<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDetail;
use App\Models\EmploymentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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

    /**
     * Get employees with birthdays today
     * For admin: Shows all employees across all companies (filtered by corp_id)
     * For non-admin: Shows only employees from their company (filtered by corp_id and company_name)
     * 
     * GET /api/birthdays/today?corp_id=xxx&company_name=xxx&empcode=xxx
     */
    public function getTodaysBirthdays(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id' => 'required|string',
            'company_name' => 'nullable|string',
            'empcode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $corpId = $request->corp_id;
            $empcode = $request->empcode;
            $companyName = $request->company_name;

            // Check if user is admin or supervisor
            $isAdmin = DB::table('userlogin')
                ->where('corp_id', $corpId)
                ->where('empcode', $empcode)
                ->where('active_yn', 1)
                ->where(function ($query) {
                    $query->where('admin_yn', 1)
                          ->orWhere('supervisor_yn', 1);
                })
                ->exists();

            // Get today's date (day and month only)
            $today = Carbon::now('Asia/Kolkata');
            $todayDay = $today->format('d');
            $todayMonth = $today->format('m');

            // Build the query
            $query = DB::table('employee_details as ed')
                ->join('employment_details as emp', function($join) {
                    $join->on('ed.corp_id', '=', 'emp.corp_id')
                         ->on('ed.EmpCode', '=', 'emp.EmpCode');
                })
                ->where('ed.corp_id', $corpId);

            // If not admin, filter by company_name
            if (!$isAdmin && $companyName) {
                $query->where('emp.company_name', $companyName);
            }

            // Get all employees and filter by birthday
            $employees = $query->select(
                    'ed.EmpCode',
                    'ed.FirstName',
                    'ed.MiddleName',
                    'ed.LastName',
                    DB::raw("CONCAT_WS(' ', ed.FirstName, ed.MiddleName, ed.LastName) as FullName"),
                    'ed.DOB',
                    'ed.WorkEmail',
                    'ed.Mobile',
                    'emp.company_name',
                    'emp.Designation'
                )
                ->get();

            // Filter employees whose birthday is today
            $birthdayEmployees = $employees->filter(function($employee) use ($todayDay, $todayMonth) {
                if (!$employee->DOB) {
                    return false;
                }

                try {
                    // Parse DOB - handle various formats (dd/mm/yyyy, dd/m/yyyy, d/m/yyyy, etc.)
                    $dobParts = explode('/', $employee->DOB);
                    if (count($dobParts) >= 2) {
                        $day = str_pad($dobParts[0], 2, '0', STR_PAD_LEFT);
                        $month = str_pad($dobParts[1], 2, '0', STR_PAD_LEFT);
                        
                        return $day === $todayDay && $month === $todayMonth;
                    }
                    return false;
                } catch (\Exception $e) {
                    return false;
                }
            })->values();

            // Calculate age for each birthday employee
            $birthdayEmployees = $birthdayEmployees->map(function($employee) use ($today) {
                try {
                    $dobParts = explode('/', $employee->DOB);
                    if (count($dobParts) === 3) {
                        $dob = Carbon::createFromFormat('d/m/Y', $employee->DOB);
                        $employee->age = $today->diffInYears($dob);
                    } else {
                        $employee->age = null;
                    }
                } catch (\Exception $e) {
                    $employee->age = null;
                }
                return $employee;
            });

            $message = $isAdmin 
                ? "Today's birthdays across all companies retrieved successfully"
                : "Today's birthdays for {$companyName} retrieved successfully";

            return response()->json([
                'status' => true,
                'message' => $message,
                'corp_id' => $corpId,
                'company_name' => $isAdmin ? 'All Companies' : $companyName,
                'is_admin' => $isAdmin,
                'today_date' => $today->format('d/m/Y'),
                'total_birthdays' => $birthdayEmployees->count(),
                'data' => $birthdayEmployees
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching birthday information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee leave summary including leave balance, absences, and sick leave
     * Filtered by corp_id, company_name, and empcode
     * 
     * GET /api/employee/leave-summary?corp_id=xxx&company_name=xxx&empcode=xxx
     */
    public function getLeaveSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id' => 'required|string',
            'company_name' => 'required|string',
            'empcode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $corpId = $request->corp_id;
            $companyName = $request->company_name;
            $empcode = $request->empcode;

            // Get current month start and end dates
            $now = Carbon::now('Asia/Kolkata');
            $monthStart = $now->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $now->copy()->endOfMonth()->format('Y-m-d');
            $currentMonth = $now->format('F Y');

            // Calculate total absences for current month from attendances table
            // Only count records where attendanceStatus is specifically 'Absent'
            // Use DISTINCT to avoid counting duplicate records for the same date
            $totalAbsences = DB::table('attendances')
                ->where('corpId', $corpId)
                ->where('empCode', $empcode)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->where('attendanceStatus', 'Absent')
                ->distinct()
                ->count('date');

            // For debugging: get sample absence records
            $sampleAbsences = DB::table('attendances')
                ->where('corpId', $corpId)
                ->where('empCode', $empcode)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->where('attendanceStatus', 'Absent')
                ->distinct()
                ->select('date', 'attendanceStatus', 'status')
                ->orderBy('date')
                ->limit(5)
                ->get();

            // Get leave policies for this corp
            $leavePolicies = DB::table('leave_policy')
                ->where('corpid', $corpId)
                ->get();

            // Calculate total leave entitlement and used leaves
            $totalLeaveEntitlement = 0;
            $sickLeaveEntitlement = 0;
            
            foreach ($leavePolicies as $policy) {
                $days = (float) $policy->toDays;
                $totalLeaveEntitlement += $days;
                
                // Check if this policy includes sick leave
                $leaveTypes = explode(',', $policy->leaveType);
                foreach ($leaveTypes as $type) {
                    if (stripos(trim($type), 'Sick') !== false) {
                        $sickLeaveEntitlement += $days;
                    }
                }
            }

            // Get approved leave requests for this employee (all time)
            $approvedLeaves = DB::table('leave_request')
                ->where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->where('empcode', $empcode)
                ->where('status', 'Approved')
                ->get();

            // Calculate total used leave days
            $totalUsedLeaves = 0;
            $sickLeaveUsed = 0;

            foreach ($approvedLeaves as $leave) {
                try {
                    // Parse dates - handle dd/mm/yyyy format
                    $fromDateStr = $leave->from_date;
                    $toDateStr = $leave->to_date;
                    
                    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $fromDateStr)) {
                        $fromDate = Carbon::createFromFormat('d/m/Y', $fromDateStr);
                        $toDate = Carbon::createFromFormat('d/m/Y', $toDateStr);
                    } else {
                        $fromDate = Carbon::parse($fromDateStr);
                        $toDate = Carbon::parse($toDateStr);
                    }
                    
                    $days = $fromDate->diffInDays($toDate) + 1;
                    $totalUsedLeaves += $days;
                    
                    // Check if it's sick leave
                    if (stripos($leave->reason, 'Sick') !== false) {
                        $sickLeaveUsed += $days;
                    }
                } catch (\Exception $e) {
                    // Skip if date parsing fails
                    continue;
                }
            }

            // Calculate balances
            $totalLeaveBalance = max(0, $totalLeaveEntitlement - $totalUsedLeaves);
            $sickLeaveBalance = max(0, $sickLeaveEntitlement - $sickLeaveUsed);

            return response()->json([
                'status' => true,
                'message' => 'Leave summary retrieved successfully',
                'corp_id' => $corpId,
                'company_name' => $companyName,
                'empcode' => $empcode,
                'current_month' => $currentMonth,
                'data' => [
                    'leave_balance' => [
                        'total_entitlement' => $totalLeaveEntitlement,
                        'total_used' => $totalUsedLeaves,
                        'total_balance' => $totalLeaveBalance
                    ],
                    'sick_leave' => [
                        'total_entitlement' => $sickLeaveEntitlement,
                        'total_used' => $sickLeaveUsed,
                        'total_balance' => $sickLeaveBalance
                    ],
                    'current_month_absences' => [
                        'month' => $currentMonth,
                        'total_absent_days' => $totalAbsences,
                        'date_range' => $monthStart . ' to ' . $monthEnd,
                        'sample_absence_dates' => $sampleAbsences->pluck('date')->toArray()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching leave summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

