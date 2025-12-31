<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeAttendanceSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmployeeAttendanceSummaryApiController extends Controller
{
    /**
     * Bulk insert attendance summary for all employees by company and month
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkInsertAttendanceSummary(Request $request)
    {
        // Validate required fields
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'month' => 'required|string|max:30',
            'year' => 'required|string|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $corpId = $request->input('corpId');
            $companyName = $request->input('companyName');
            $month = $request->input('month');
            $year = $request->input('year');

            // Check if data already exists for this period
            $existingRecords = EmployeeAttendanceSummary::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('month', $month)
                ->where('year', $year)
                ->count();

            if ($existingRecords > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance summary already exists for this period',
                    'period' => $month . ' ' . $year,
                    'company' => $companyName,
                    'corpId' => $corpId,
                    'existing_records' => $existingRecords
                ], 409);
            }

            // Get all attendance data for the specified period
            $attendanceData = DB::table('attendances')
                ->select('empCode', 'attendanceStatus', 'date')
                ->where('companyName', $companyName)
                ->where('corpId', $corpId)
                ->whereRaw("DATE_FORMAT(date, '%M') = ?", [$month])
                ->whereRaw("YEAR(date) = ?", [$year])
                ->get();

            if ($attendanceData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No attendance data found for the specified period'
                ], 404);
            }

            // Get holiday dates for the month
            $holidays = DB::table('holiday_lists')
                ->select('holidayDate')
                ->where('corpId', $corpId)
                ->whereRaw("DATE_FORMAT(holidayDate, '%M') = ?", [$month])
                ->whereRaw("YEAR(holidayDate) = ?", [$year])
                ->count();

            // Get company shift policy
            $companyShiftPolicy = DB::table('company_shift_policy')
                ->select('shift_code')
                ->where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->first();

            if (!$companyShiftPolicy) {
                return response()->json([
                    'status' => false,
                    'message' => 'Company shift policy not found'
                ], 404);
            }

            // Get shift policy PUID
            $shiftPolicy = DB::table('shiftpolicy')
                ->select('puid')
                ->where('shift_code', $companyShiftPolicy->shift_code)
                ->where('corp_id', $corpId)
                ->first();

            if (!$shiftPolicy) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift policy not found'
                ], 404);
            }

            // Check if the month has 5 weeks
            $firstDayOfMonth = Carbon::create($year, Carbon::parse($month)->month, 1);
            $lastDayOfMonth = $firstDayOfMonth->copy()->endOfMonth();
            $totalWeeks = $firstDayOfMonth->weekOfYear !== $lastDayOfMonth->weekOfYear ?
                $lastDayOfMonth->weekOfMonth : $firstDayOfMonth->weekOfMonth;
            $hasFiveWeeks = $totalWeeks >= 5;

            // Get weekly schedule and calculate week-off days
            $weeklyScheduleQuery = DB::table('shift_policy_weekly_schedule')
                ->select('time', 'week_no')
                ->where('puid', $shiftPolicy->puid);

            // Exclude "Week 5" if the month doesn't have 5 weeks
            if (!$hasFiveWeeks) {
                $weeklyScheduleQuery->where('week_no', '!=', 'Week 5');
            }

            $weeklySchedule = $weeklyScheduleQuery->get();

            $weekOffCount = 0;
            foreach ($weeklySchedule as $schedule) {
                if ($schedule->time === 'Full Day') {
                    $weekOffCount += 1;
                } elseif ($schedule->time === 'Half Day') {
                    $weekOffCount += 0.5;
                }
            }

            // Get leave requests for the period
            $monthNumber = Carbon::parse($month)->month;
            $startDateCarbon = Carbon::create($year, $monthNumber, 1)->startOfDay();
            $endDateCarbon = Carbon::create($year, $monthNumber, 1)->endOfMonth()->endOfDay();

            // Fetch all leave requests for this corp/company and filter in PHP
            // because dates are stored as DD/MM/YYYY strings which don't work with SQL date comparisons
            $leaveRequests = DB::table('leave_request')
                ->select('empcode', 'from_date', 'to_date', 'status')
                ->where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->get();

            // Create a map of leave status by empCode and date
            $leaveStatusMap = [];
            foreach ($leaveRequests as $leave) {
                $fromDateStr = $leave->from_date;
                $toDateStr = $leave->to_date;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fromDateStr)) {
                    $fromDate = Carbon::createFromFormat('d/m/Y', $fromDateStr)->startOfDay();
                } else {
                    $fromDate = Carbon::parse($fromDateStr)->startOfDay();
                }
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $toDateStr)) {
                    $toDate = Carbon::createFromFormat('d/m/Y', $toDateStr)->startOfDay();
                } else {
                    $toDate = Carbon::parse($toDateStr)->startOfDay();
                }

                // Check if this leave request overlaps with the target month
                if ($toDate->lt($startDateCarbon) || $fromDate->gt($endDateCarbon)) {
                    // Leave is entirely outside the target month, skip
                    continue;
                }

                for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                    $dateKey = $date->format('Y-m-d');
                    // Only count days that fall within the target month
                    if ($date->gte($startDateCarbon) && $date->lte($endDateCarbon)) {
                        $leaveStatusMap[$leave->empcode][$dateKey] = $leave->status;
                    }
                }
            }

            // Get actual holiday dates for the month (to skip them in processing)
            $holidayDates = DB::table('holiday_lists')
                ->where('corpId', $corpId)
                ->whereRaw("DATE_FORMAT(holidayDate, '%M') = ?", [$month])
                ->whereRaw("YEAR(holidayDate) = ?", [$year])
                ->pluck('holidayDate')
                ->map(function($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->toArray();

            // Get week-off days from shift policy (to skip them in processing)
            $weekOffDays = DB::table('shift_policy_weekly_schedule')
                ->select('day_name', 'week_no', 'time')
                ->where('puid', $shiftPolicy->puid)
                ->where('time', 'Full Day')
                ->get();

            // Calculate which dates in the month are week-offs
            $monthNumber = Carbon::parse($month)->month;
            $weekOffDates = [];
            $firstDayOfMonth = Carbon::create($year, $monthNumber, 1);
            $lastDayOfMonth = $firstDayOfMonth->copy()->endOfMonth();
            
            for ($date = $firstDayOfMonth->copy(); $date->lte($lastDayOfMonth); $date->addDay()) {
                $dayName = $date->format('l'); // Get day name (Sunday, Monday, etc.)
                $weekNumber = $date->weekOfMonth;
                $weekNoString = 'Week ' . $weekNumber;
                
                // Check if this day is a week-off
                foreach ($weekOffDays as $weekOff) {
                    if ($weekOff->day_name === $dayName && $weekOff->week_no === $weekNoString) {
                        $weekOffDates[] = $date->format('Y-m-d');
                        break;
                    }
                }
            }

            // Combine holidays and week-offs to skip
            $daysToSkip = array_unique(array_merge($holidayDates, $weekOffDates));

            // Get unique employee codes from attendance data
            $empCodes = $attendanceData->pluck('empCode')->unique();

            $summaryData = [];
            $processedCount = 0;

            foreach ($empCodes as $empCode) {
                // Calculate attendance statistics for this employee
                $employeeAttendance = $attendanceData->where('empCode', $empCode);

                $totalPresent = 0;
                $totalLeave = 0;           // Approved leaves (Paid)
                $totalAbsent = 0;          // Total absent days
                $absentWithLeave = 0;      // Absent with leave request (not approved but Paid)
                $absentWithoutLeave = 0;   // Absent without leave request (LOP - Unpaid)

                foreach ($employeeAttendance as $attendance) {
                    $dateString = $attendance->date;
                    $dateObj = null;
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateString)) {
                        $dateObj = Carbon::createFromFormat('d/m/Y', $dateString);
                    } else {
                        $dateObj = Carbon::parse($dateString);
                    }
                    $attendanceDate = $dateObj->format('Y-m-d');

                    // Skip holidays and week-offs
                    if (in_array($attendanceDate, $daysToSkip)) {
                        continue;
                    }

                    $leaveStatus = $leaveStatusMap[$empCode][$attendanceDate] ?? null;

                    // Determine the status based on leave request and attendance
                    if ($leaveStatus === 'Approved') {
                        // Approved leave counts as Leave (Paid)
                        $totalLeave++;
                    } elseif (in_array($leaveStatus, ['Pending', 'Rejected', 'Returned'])) {
                        // Leave request exists but not approved - counts as Absent but still Paid
                        $totalAbsent++;
                        $absentWithLeave++;
                    } elseif ($attendance->attendanceStatus === 'Present') {
                        // Employee was present (Paid)
                        $totalPresent++;
                    } elseif ($attendance->attendanceStatus === 'Leave') {
                        // Direct leave entry in attendance (Paid)
                        $totalLeave++;
                    } else {
                        // Absent without any leave request - Loss of Pay (Unpaid)
                        $totalAbsent++;
                        $absentWithoutLeave++;
                    }
                }

                // Calculate working days correctly using actual non-working dates
                $totalDaysInMonth = Carbon::createFromFormat('F Y', $month . ' ' . $year)->daysInMonth;
                $workingDays = $totalDaysInMonth - count($daysToSkip);
                
                // Store actual counts for the response
                $actualHolidayCount = count($holidayDates);
                $actualWeekOffCount = count($weekOffDates);

                // Calculate paid days: Present + Approved Leave + Absent with Leave (not approved but applied)
                // Only deduct absent without leave (LOP days)
                $paidDays = $workingDays - $absentWithoutLeave;
                
                // Loss of Pay days = Absent without leave request
                $lopDays = $absentWithoutLeave;

                $summaryData[] = [
                    'corpId' => $corpId,
                    'empCode' => $empCode,
                    'companyName' => $companyName,
                    'totalPresent' => $totalPresent,
                    'workingDays' => $workingDays,
                    'holidays' => $actualHolidayCount,
                    'weekOff' => $actualWeekOffCount,
                    'leave' => $totalLeave,
                    'paidDays' => $paidDays,
                    'absent' => $totalAbsent,
                    'month' => $month,
                    'year' => $year,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $processedCount++;
            }

            // Bulk insert the summary data
            EmployeeAttendanceSummary::insert($summaryData);

            return response()->json([
                'status' => true,
                'message' => 'Attendance summary created successfully for all employees',
                'summary' => [
                    'total_employees_processed' => $processedCount,
                    'period' => "{$month} {$year}",
                    'company' => $companyName,
                    'corpId' => $corpId,
                    'holidays_in_period' => $actualHolidayCount ?? 0,
                    'week_off_days' => $actualWeekOffCount ?? 0,
                    'working_days_calculated' => $workingDays ?? 0
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while processing attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update attendance summary by ID
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'corpId' => 'sometimes|string|max:10',
            'empCode' => 'sometimes|string|max:20',
            'companyName' => 'sometimes|string|max:100',
            'totalPresent' => 'sometimes|integer|min:0',
            'workingDays' => 'sometimes|integer|min:0',
            'holidays' => 'sometimes|integer|min:0',
            'weekOff' => 'sometimes|integer|min:0',
            'leave' => 'sometimes|numeric|min:0',
            'month' => 'sometimes|string|max:30',
            'year' => 'sometimes|string|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find the attendance summary record
            $attendanceSummary = EmployeeAttendanceSummary::find($id);

            if (!$attendanceSummary) {
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance summary record not found'
                ], 404);
            }

            // Update with provided data
            $attendanceSummary->update($request->only([
                'corpId',
                'empCode',
                'companyName',
                'totalPresent',
                'workingDays',
                'holidays',
                'weekOff',
                'leave',
                'month',
                'year'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Attendance summary updated successfully',
                'data' => $attendanceSummary->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete attendance summary by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Find the attendance summary record
            $attendanceSummary = EmployeeAttendanceSummary::find($id);

            if (!$attendanceSummary) {
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance summary record not found'
                ], 404);
            }

            // Store information before deletion for response
            $deletedInfo = [
                'id' => $attendanceSummary->id,
                'empCode' => $attendanceSummary->empCode,
                'companyName' => $attendanceSummary->companyName,
                'period' => "{$attendanceSummary->month} {$attendanceSummary->year}"
            ];

            // Delete the record
            $attendanceSummary->delete();

            return response()->json([
                'status' => true,
                'message' => 'Attendance summary deleted successfully',
                'deleted_record' => $deletedInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all attendance summaries with optional filtering
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate input parameters
            $validator = Validator::make($request->all(), [
                'corpId' => 'sometimes|string|max:10',
                'companyName' => 'sometimes|string|max:100',
                'empCode' => 'sometimes|string|max:20',
                'month' => 'sometimes|string|max:30',
                'year' => 'sometimes|string|max:4',
                'per_page' => 'sometimes|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Join with employee_details to get employee name information
            $query = DB::table('employee_attendance_summary as eas')
                ->leftJoin('employee_details as ed', function($join) {
                    $join->on('eas.empCode', '=', 'ed.EmpCode')
                         ->on('eas.corpId', '=', 'ed.corp_id');
                })
                ->select(
                    'eas.*',
                    'ed.FirstName',
                    'ed.MiddleName',
                    'ed.LastName',
                    DB::raw("TRIM(CONCAT(COALESCE(ed.FirstName, ''), ' ', COALESCE(ed.MiddleName, ''), ' ', COALESCE(ed.LastName, ''))) as employeeFullName"),
                    DB::raw("CONCAT(
                        COALESCE(SUBSTRING(ed.FirstName, 1, 1), ''),
                        COALESCE(SUBSTRING(ed.MiddleName, 1, 1), ''),
                        COALESCE(SUBSTRING(ed.LastName, 1, 1), '')
                    ) as nameInitials")
                );

            $appliedFilters = [];

            // Apply filters if provided
            if ($request->filled('corpId')) {
                $query->where('eas.corpId', $request->corpId);
                $appliedFilters['corpId'] = $request->corpId;
            }

            if ($request->filled('companyName')) {
                $query->where('eas.companyName', $request->companyName);
                $appliedFilters['companyName'] = $request->companyName;
            }

            if ($request->filled('empCode')) {
                $query->where('eas.empCode', $request->empCode);
                $appliedFilters['empCode'] = $request->empCode;
            }

            if ($request->filled('month')) {
                $query->where('eas.month', $request->month);
                $appliedFilters['month'] = $request->month;
            }

            if ($request->filled('year')) {
                $query->where('eas.year', $request->year);
                $appliedFilters['year'] = $request->year;
            }

            // Get paginated results
            $perPage = $request->input('per_page', 15);
            $attendanceSummaries = $query->orderBy('eas.created_at', 'desc')->paginate($perPage);

            if ($attendanceSummaries->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No attendance summary records found',
                    'applied_filters' => $appliedFilters,
                    'data' => []
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Attendance summaries retrieved successfully',
                'applied_filters' => $appliedFilters,
                'total_records' => $attendanceSummaries->total(),
                'data' => $attendanceSummaries
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving attendance summaries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific attendance summary by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $attendanceSummary = EmployeeAttendanceSummary::find($id);

            if (!$attendanceSummary) {
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance summary record not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Attendance summary retrieved successfully',
                'data' => $attendanceSummary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk insert attendance summary with fallback for missing shift policy
     * Uses Saturday/Sunday as default weekends when shift policy is not found
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkInsertAttendanceSummaryWithFallback(Request $request)
    {
        // Validate required fields
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'month' => 'required|string|max:30',
            'year' => 'required|string|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $corpId = $request->input('corpId');
            $companyName = $request->input('companyName');
            $month = $request->input('month');
            $year = $request->input('year');

            // Check if data already exists for this period
            $existingRecords = EmployeeAttendanceSummary::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('month', $month)
                ->where('year', $year)
                ->count();

            if ($existingRecords > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance summary already exists for this period',
                    'period' => $month . ' ' . $year,
                    'company' => $companyName,
                    'corpId' => $corpId,
                    'existing_records' => $existingRecords
                ], 409);
            }

            // Get all attendance data for the specified period
            $attendanceData = DB::table('attendances')
                ->select('empCode', 'attendanceStatus', 'date')
                ->where('companyName', $companyName)
                ->where('corpId', $corpId)
                ->whereRaw("DATE_FORMAT(date, '%M') = ?", [$month])
                ->whereRaw("YEAR(date) = ?", [$year])
                ->get();

            if ($attendanceData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No attendance data found for the specified period'
                ], 404);
            }

            // Get holiday dates for the month
            $holidays = DB::table('holiday_lists')
                ->select('holidayDate')
                ->where('corpId', $corpId)
                ->whereRaw("DATE_FORMAT(holidayDate, '%M') = ?", [$month])
                ->whereRaw("YEAR(holidayDate) = ?", [$year])
                ->count();

            // Try to get company shift policy
            $companyShiftPolicy = DB::table('company_shift_policy')
                ->select('shift_code')
                ->where('company_name', $companyName)
                ->where('corp_id', $corpId)
                ->first();

            $weekOffCount = 0;
            $usedFallback = false;

            if (!$companyShiftPolicy) {
                // Fallback: Use Saturday/Sunday as weekends
                $usedFallback = true;
                $weekOffCount = $this->calculateWeekendDaysWithFallback($month, $year);
            } else {
                // Try to get shift policy PUID
                $shiftPolicy = DB::table('shiftpolicy')
                    ->select('puid')
                    ->where('shift_code', $companyShiftPolicy->shift_code)
                    ->where('corp_id', $corpId)
                    ->first();

                if (!$shiftPolicy) {
                    // Fallback: Use Saturday/Sunday as weekends
                    $usedFallback = true;
                    $weekOffCount = $this->calculateWeekendDaysWithFallback($month, $year);
                } else {
                    // Use shift policy logic
                    $weekOffCount = $this->calculateWeekOffFromShiftPolicy($shiftPolicy->puid, $month, $year);
                }
            }

            // Get leave requests for the period
            $monthNumber = Carbon::parse($month)->month;
            $startDateCarbon = Carbon::create($year, $monthNumber, 1)->startOfDay();
            $endDateCarbon = Carbon::create($year, $monthNumber, 1)->endOfMonth()->endOfDay();

            // Fetch all leave requests for this corp/company and filter in PHP
            // because dates are stored as DD/MM/YYYY strings which don't work with SQL date comparisons
            $leaveRequests = DB::table('leave_request')
                ->select('empcode', 'from_date', 'to_date', 'status')
                ->where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->get();

            // Create a map of leave status by empCode and date
            $leaveStatusMap = [];
            foreach ($leaveRequests as $leave) {
                $fromDateStr = $leave->from_date;
                $toDateStr = $leave->to_date;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fromDateStr)) {
                    $fromDate = Carbon::createFromFormat('d/m/Y', $fromDateStr)->startOfDay();
                } else {
                    $fromDate = Carbon::parse($fromDateStr)->startOfDay();
                }
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $toDateStr)) {
                    $toDate = Carbon::createFromFormat('d/m/Y', $toDateStr)->startOfDay();
                } else {
                    $toDate = Carbon::parse($toDateStr)->startOfDay();
                }

                // Check if this leave request overlaps with the target month
                if ($toDate->lt($startDateCarbon) || $fromDate->gt($endDateCarbon)) {
                    // Leave is entirely outside the target month, skip
                    continue;
                }

                for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                    $dateKey = $date->format('Y-m-d');
                    // Only count days that fall within the target month
                    if ($date->gte($startDateCarbon) && $date->lte($endDateCarbon)) {
                        $leaveStatusMap[$leave->empcode][$dateKey] = $leave->status;
                    }
                }
            }

            // Get holiday and week-off dates for the month (once for all employees)
            $holidayDates = DB::table('holiday_lists')
                ->where('corpId', $corpId)
                ->whereRaw("DATE_FORMAT(holidayDate, '%M') = ?", [$month])
                ->whereRaw("YEAR(holidayDate) = ?", [$year])
                ->pluck('holidayDate')
                ->toArray();

            // Determine $puid for shift policy lookup
            $companyShiftPolicy = DB::table('company_shift_policy')
                ->select('shift_code')
                ->where('company_name', $companyName)
                ->where('corp_id', $corpId)
                ->first();

            $puid = null;
            $usedFallback = true;

            if ($companyShiftPolicy) {
                $shiftPolicy = DB::table('shiftpolicy')
                    ->select('puid')
                    ->where('shift_code', $companyShiftPolicy->shift_code)
                    ->where('corp_id', $corpId)
                    ->first();

                if ($shiftPolicy) {
                    $puid = $shiftPolicy->puid;
                    $usedFallback = false;
                }
            }

            $weekOffDates = $this->getWeekOffDatesForMonth($puid, $month, $year, $usedFallback);
            $nonWorkingDates = array_unique(array_merge($holidayDates, $weekOffDates));

            // Calculate working days (total days in month - non-working days)
            $totalDaysInMonth = Carbon::createFromFormat('F Y', $month . ' ' . $year)->daysInMonth;
            $workingDays = $totalDaysInMonth - count($nonWorkingDates);

            // Get unique employee codes from attendance data
            $empCodes = $attendanceData->pluck('empCode')->unique();

            $summaryData = [];
            foreach ($empCodes as $empCode) {
                // Calculate attendance statistics for this employee
                $employeeAttendance = $attendanceData->where('empCode', $empCode);

                $totalPresent = 0;
                $totalLeave = 0;           // Approved leaves (Paid)
                $totalAbsent = 0;          // Total absent days
                $absentWithLeave = 0;      // Absent with leave request (not approved but Paid)
                $absentWithoutLeave = 0;   // Absent without leave request (LOP - Unpaid)

                foreach ($employeeAttendance as $attendance) {
                    $dateString = $attendance->date;
                    $dateObj = null;
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateString)) {
                        $dateObj = Carbon::createFromFormat('d/m/Y', $dateString);
                    } else {
                        $dateObj = Carbon::parse($dateString);
                    }
                    $attendanceDate = $dateObj->format('Y-m-d');

                    // Skip non-working days from any calculation
                    if (in_array($attendanceDate, $nonWorkingDates)) {
                        continue;
                    }

                    $leaveStatus = $leaveStatusMap[$empCode][$attendanceDate] ?? null;

                    // Determine the status based on leave request and attendance
                    if ($leaveStatus === 'Approved') {
                        // Approved leave counts as Leave (Paid)
                        $totalLeave++;
                    } elseif (in_array($leaveStatus, ['Pending', 'Rejected', 'Returned'])) {
                        // Leave request exists but not approved - counts as Absent but still Paid
                        $totalAbsent++;
                        $absentWithLeave++;
                    } elseif ($attendance->attendanceStatus === 'Present') {
                        // Employee was present (Paid)
                        $totalPresent++;
                    } elseif ($attendance->attendanceStatus === 'Leave') {
                        // Direct leave entry in attendance (Paid)
                        $totalLeave++;
                    } else {
                        // Absent without any leave request - Loss of Pay (Unpaid)
                        $totalAbsent++;
                        $absentWithoutLeave++;
                    }
                }

                // Calculate paid days: Present + Approved Leave + Absent with Leave (not approved but applied)
                // Only deduct absent without leave (LOP days)
                $paidDays = $workingDays - $absentWithoutLeave;

                $summaryData[] = [
                    'corpId' => $corpId,
                    'empCode' => $empCode,
                    'companyName' => $companyName,
                    'totalPresent' => $totalPresent,
                    'workingDays' => $workingDays,
                    'holidays' => count($holidayDates),
                    'weekOff' => count($weekOffDates),
                    'leave' => $totalLeave,
                    'paidDays' => $paidDays,
                    'absent' => $totalAbsent,
                    'month' => $month,
                    'year' => $year,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert
            EmployeeAttendanceSummary::insert($summaryData);

            return response()->json([
                'status' => true,
                'message' => 'Attendance summary created successfully for all employees' . ($usedFallback ? ' (using fallback weekend logic)' : ''),
                'summary' => [
                    'total_employees_processed' => count($empCodes),
                    'period' => $month . ' ' . $year,
                    'company' => $companyName,
                    'corpId' => $corpId,
                    'holidays_in_period' => count($holidayDates),
                    'week_off_days' => count($weekOffDates),
                    'working_days_calculated' => $workingDays,
                    'used_fallback_logic' => $usedFallback,
                    'fallback_note' => $usedFallback ? 'Saturday and Sunday treated as weekends' : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while processing attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate weekend days using Saturday/Sunday fallback logic
     *
     * @param string $month
     * @param string $year
     * @return int
     */
    private function calculateWeekendDaysWithFallback($month, $year)
    {
        $firstDay = Carbon::createFromFormat('F Y', $month . ' ' . $year)->startOfMonth();
        $lastDay = $firstDay->copy()->endOfMonth();

        $weekendCount = 0;
        $current = $firstDay->copy();

        while ($current->lte($lastDay)) {
            if ($current->isSaturday() || $current->isSunday()) {
                $weekendCount++;
            }
            $current->addDay();
        }

        return $weekendCount;
    }

    /**
     * Calculate week off from shift policy with Week 5 validation
     *
     * @param string $puid
     * @param string $month
     * @param string $year
     * @return int
     */
    private function calculateWeekOffFromShiftPolicy($puid, $month, $year)
    {
        // Check if the month has 5 weeks
        $firstDayOfMonth = Carbon::create($year, Carbon::parse($month)->month, 1);
        $lastDayOfMonth = $firstDayOfMonth->copy()->endOfMonth();
        $totalWeeks = $firstDayOfMonth->weekOfYear !== $lastDayOfMonth->weekOfYear ?
                     $lastDayOfMonth->weekOfMonth : $firstDayOfMonth->weekOfMonth;
        $hasFiveWeeks = $totalWeeks >= 5;

        // Get weekly schedule and calculate week-off days
        $weeklyScheduleQuery = DB::table('shift_policy_weekly_schedule')
            ->select('time', 'week_no')
            ->where('puid', $puid);

        // Exclude "Week 5" if the month doesn't have 5 weeks
        if (!$hasFiveWeeks) {
            $weeklyScheduleQuery->where('week_no', '!=', 'Week 5');
        }

        $weeklySchedule = $weeklyScheduleQuery->get();

        $weekOffCount = 0;
        foreach ($weeklySchedule as $schedule) {
            if ($schedule->time === 'Full Day') {
                $weekOffCount += 1;
            } elseif ($schedule->time === 'Half Day') {
                $weekOffCount += 0.5;
            }
        }

        return $weekOffCount;
    }

    /**
     * Check if attendance summary exists for given parameters (route-based)
     *
     * @param string $corpId
     * @param string $companyName
     * @param string $month
     * @param string $year
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAttendanceSummaryExistsByRoute($corpId, $companyName, $month, $year)
    {
        try {
            // Check if attendance summary exists
            $exists = EmployeeAttendanceSummary::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('month', $month)
                ->where('year', $year)
                ->exists();

            return response()->json([
                'status' => $exists
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while checking attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all week-off dates for a given month based on shift policy or fallback
     *
     * @param string|null $puid
     * @param string $month
     * @param string $year
     * @param bool $useFallback
     * @return array
     */
    private function getWeekOffDatesForMonth($puid, $month, $year, $useFallback)
    {
        $dates = [];
        $firstDay = Carbon::createFromFormat('F Y', $month . ' ' . $year)->startOfMonth();
        $lastDay = $firstDay->copy()->endOfMonth();

        if ($useFallback || !$puid) {
            // Fallback: Saturday and Sunday are week-offs
            for ($date = $firstDay->copy(); $date->lte($lastDay); $date->addDay()) {
                if ($date->isSaturday() || $date->isSunday()) {
                    $dates[] = $date->format('Y-m-d');
                }
            }
            return $dates;
        }

        // Get weekly schedule from shift policy
        $weeklySchedule = DB::table('shift_policy_weekly_schedule')
            ->where('puid', $puid)
            ->where('time', 'Full Day')  // Only get full day offs
            ->get();

        // Create a map: week_no => [day_names that are off]
        $scheduleMap = [];
        foreach ($weeklySchedule as $week) {
            if (!isset($scheduleMap[$week->week_no])) {
                $scheduleMap[$week->week_no] = [];
            }
            $scheduleMap[$week->week_no][] = $week->day_name;
        }

        for ($date = $firstDay->copy(); $date->lte($lastDay); $date->addDay()) {
            $weekOfMonth = 'Week ' . $date->weekOfMonth;
            $dayName = $date->format('l'); // Sunday, Monday, etc.

            if (isset($scheduleMap[$weekOfMonth]) && in_array($dayName, $scheduleMap[$weekOfMonth])) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }

    /**
     * Recalculate attendance summaries for a given period.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recalculateAttendanceSummaries(Request $request)
    {
        // Validate required fields
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'month' => 'required|string|max:30',
            'year' => 'required|string|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $corpId = $request->input('corpId');
            $companyName = $request->input('companyName');
            $month = $request->input('month');
            $year = $request->input('year');

            // Get existing summary records
            $existingSummaries = EmployeeAttendanceSummary::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('month', $month)
                ->where('year', $year)
                ->get();

            if ($existingSummaries->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'No existing attendance summaries found for the specified period to recalculate.'], 404);
            }

            // --- Recalculation Logic (adapted from bulkInsert) ---

            // Get holiday dates for the month
            $holidayDates = DB::table('holiday_lists')
                ->where('corpId', $corpId)
                ->whereRaw("DATE_FORMAT(holidayDate, '%M') = ?", [$month])
                ->whereRaw("YEAR(holidayDate) = ?", [$year])
                ->pluck('holidayDate')
                ->toArray();

            // Get company shift policy
            $companyShiftPolicy = DB::table('company_shift_policy')->select('shift_code')->where('company_name', $companyName)->where('corp_id', $corpId)->first();
            $puid = null;
            $usedFallback = true;
            if ($companyShiftPolicy) {
                $shiftPolicy = DB::table('shiftpolicy')->select('puid')->where('shift_code', $companyShiftPolicy->shift_code)->where('corp_id', $corpId)->first();
                if ($shiftPolicy) {
                    $puid = $shiftPolicy->puid;
                    $usedFallback = false;
                }
            }

            $weekOffDates = $this->getWeekOffDatesForMonth($puid, $month, $year, $usedFallback);
            $nonWorkingDates = array_unique(array_merge($holidayDates, $weekOffDates));

            $totalDaysInMonth = Carbon::createFromFormat('F Y', $month . ' ' . $year)->daysInMonth;
            $workingDays = $totalDaysInMonth - count($nonWorkingDates);

            // Get all attendance and leave data for the period to avoid querying in a loop
            $attendanceData = DB::table('attendances')
                ->where('companyName', $companyName)
                ->where('corpId', $corpId)
                ->whereRaw("DATE_FORMAT(date, '%M') = ?", [$month])
                ->whereRaw("YEAR(date) = ?", [$year])
                ->get()->groupBy('empCode');

            $monthNumber = Carbon::parse($month)->month;

            // Fetch all leave requests for this corp/company and filter in PHP
            // because dates are stored as DD/MM/YYYY strings which don't work with SQL date comparisons
            $leaveRequests = DB::table('leave_request')
                ->where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->get();

            $startDateCarbon = Carbon::create($year, $monthNumber, 1)->startOfDay();
            $endDateCarbon = Carbon::create($year, $monthNumber, 1)->endOfMonth()->endOfDay();

            $leaveStatusMap = [];
            foreach ($leaveRequests as $leave) {
                // Parse from_date with DD/MM/YYYY format support
                $fromDateStr = $leave->from_date;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fromDateStr)) {
                    $fromDate = Carbon::createFromFormat('d/m/Y', $fromDateStr)->startOfDay();
                } else {
                    $fromDate = Carbon::parse($fromDateStr)->startOfDay();
                }
                
                // Parse to_date with DD/MM/YYYY format support
                $toDateStr = $leave->to_date;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $toDateStr)) {
                    $toDate = Carbon::createFromFormat('d/m/Y', $toDateStr)->startOfDay();
                } else {
                    $toDate = Carbon::parse($toDateStr)->startOfDay();
                }
                
                // Check if this leave request overlaps with the target month
                if ($toDate->lt($startDateCarbon) || $fromDate->gt($endDateCarbon)) {
                    // Leave is entirely outside the target month, skip
                    continue;
                }
                
                for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                    $dateKey = $date->format('Y-m-d');
                    // Only count days that fall within the target month
                    if ($date->gte($startDateCarbon) && $date->lte($endDateCarbon)) {
                        $leaveStatusMap[$leave->empcode][$dateKey] = $leave->status;
                    }
                }
            }

            $updatedCount = 0;
            foreach ($existingSummaries as $summary) {
                $empCode = $summary->empCode;
                $employeeAttendance = $attendanceData->get($empCode) ?? collect();

                $totalPresent = 0;
                $totalLeave = 0;           // Approved leaves (Paid)
                $totalAbsent = 0;          // Total absent days
                $absentWithLeave = 0;      // Absent with leave request (not approved but Paid)
                $absentWithoutLeave = 0;   // Absent without leave request (LOP - Unpaid)

                foreach ($employeeAttendance as $attendance) {
                    $attendanceDate = Carbon::parse($attendance->date)->format('Y-m-d');
                    if (in_array($attendanceDate, $nonWorkingDates)) continue;

                    $leaveStatus = $leaveStatusMap[$empCode][$attendanceDate] ?? null;

                    // Determine the status based on leave request and attendance
                    if ($leaveStatus === 'Approved') {
                        // Approved leave counts as Leave (Paid)
                        $totalLeave++;
                    } elseif (in_array($leaveStatus, ['Pending', 'Rejected', 'Returned'])) {
                        // Leave request exists but not approved - counts as Absent but still Paid
                        $totalAbsent++;
                        $absentWithLeave++;
                    } elseif ($attendance->attendanceStatus === 'Present') {
                        // Employee was present (Paid)
                        $totalPresent++;
                    } elseif ($attendance->attendanceStatus === 'Leave') {
                        // Direct leave entry in attendance (Paid)
                        $totalLeave++;
                    } else {
                        // Absent without any leave request - Loss of Pay (Unpaid)
                        $totalAbsent++;
                        $absentWithoutLeave++;
                    }
                }

                // Calculate paid days: Present + Approved Leave + Absent with Leave (not approved but applied)
                // Only deduct absent without leave (LOP days)
                $paidDays = $workingDays - $absentWithoutLeave;

                // Update the record
                $summary->update([
                    'totalPresent' => $totalPresent,
                    'workingDays' => $workingDays,
                    'holidays' => count($holidayDates),
                    'weekOff' => count($weekOffDates),
                    'leave' => $totalLeave,
                    'paidDays' => $paidDays,
                    'absent' => $totalAbsent,
                ]);
                $updatedCount++;
            }

            return response()->json([
                'status' => true,
                'message' => "Successfully recalculated attendance for {$updatedCount} employees.",
                'period' => "{$month} {$year}",
                'company' => $companyName,
            ]);

        } catch (\Exception $e) {
            Log::error("Recalculation Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'An error occurred during recalculation.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export employee attendance summary to Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportAttendanceSummary(Request $request)
    {
        // Validate required fields
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $corpId = $request->input('corpId');
            $companyName = $request->input('companyName');
            $year = $request->input('year');
            $month = $request->input('month');

            // Fetch attendance summary data with employee details
            $attendanceData = DB::table('employee_attendance_summary as eas')
                ->leftJoin('employee_details as ed', function($join) {
                    $join->on('eas.empCode', '=', 'ed.EmpCode')
                         ->on('eas.corpId', '=', 'ed.corp_id');
                })
                ->leftJoin('employment_details as emp', function($join) {
                    $join->on('eas.empCode', '=', 'emp.EmpCode')
                         ->on('eas.corpId', '=', 'emp.corp_id');
                })
                ->select(
                    'eas.id',
                    'eas.corpId',
                    'eas.empCode',
                    'eas.companyName',
                    DB::raw("TRIM(CONCAT(COALESCE(ed.FirstName, ''), ' ', COALESCE(ed.MiddleName, ''), ' ', COALESCE(ed.LastName, ''))) as employeeName"),
                    'emp.Designation',
                    'emp.Department',
                    'eas.totalPresent',
                    'eas.workingDays',
                    'eas.holidays',
                    'eas.weekOff',
                    'eas.leave',
                    'eas.paidDays',
                    'eas.absent',
                    'eas.month',
                    'eas.year'
                )
                ->where('eas.corpId', $corpId)
                ->where('eas.companyName', $companyName)
                ->where('eas.year', $year)
                ->where('eas.month', $month)
                ->orderBy('eas.empCode')
                ->get();

            if ($attendanceData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No attendance summary found for the specified criteria',
                    'filter' => "corpId: {$corpId}, companyName: {$companyName}, year: {$year}, month: {$month}"
                ], 404);
            }

            // Create a new spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set header row
            $headers = [
                'ID',
                'Corp ID',
                'Employee Code',
                'Employee Name',
                'Designation',
                'Department',
                'Company Name',
                'Total Present',
                'Working Days',
                'Holidays',
                'Week Off',
                'Leave',
                'Paid Days',
                'Absent',
                'Month',
                'Year'
            ];

            $sheet->fromArray($headers, null, 'A1');

            // Style header row
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ];
            $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

            // Add data rows
            $row = 2;
            foreach ($attendanceData as $data) {
                $sheet->fromArray([
                    $data->id,
                    $data->corpId,
                    $data->empCode,
                    $data->employeeName ?: 'N/A',
                    $data->Designation ?: 'N/A',
                    $data->Department ?: 'N/A',
                    $data->companyName,
                    $data->totalPresent,
                    $data->workingDays,
                    $data->holidays,
                    $data->weekOff,
                    $data->leave,
                    $data->paidDays,
                    $data->absent,
                    $data->month,
                    $data->year
                ], null, 'A' . $row);
                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'P') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Create Excel file
            $writer = new Xlsx($spreadsheet);
            $filename = "Attendance_Summary_{$corpId}_{$companyName}_{$month}_{$year}.xlsx";
            $tempFile = tempnam(sys_get_temp_dir(), 'attendance_export_');
            $writer->save($tempFile);

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error exporting attendance summary: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while exporting attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export employee attendance summary to Excel (GET method with URL parameters)
     * Returns JSON with a link to download the file.
     *
     * @param string $corpId
     * @param string $companyName
     * @param string $year
     * @param string $month
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportAttendanceSummaryGet($corpId, $companyName, $year, $month)
    {
        try {
            // URL decode the company name as it may contain spaces
            $companyName = urldecode($companyName);

            // Fetch attendance summary data with employee details
            $attendanceData = DB::table('employee_attendance_summary as eas')
                ->leftJoin('employee_details as ed', function($join) {
                    $join->on('eas.empCode', '=', 'ed.EmpCode')
                         ->on('eas.corpId', '=', 'ed.corp_id');
                })
                ->leftJoin('employment_details as emp', function($join) {
                    $join->on('eas.empCode', '=', 'emp.EmpCode')
                         ->on('eas.corpId', '=', 'emp.corp_id');
                })
                ->select(
                    'eas.id',
                    'eas.corpId',
                    'eas.empCode',
                    'eas.companyName',
                    DB::raw("TRIM(CONCAT(COALESCE(ed.FirstName, ''), ' ', COALESCE(ed.MiddleName, ''), ' ', COALESCE(ed.LastName, ''))) as employeeName"),
                    'emp.Designation',
                    'emp.Department',
                    'eas.totalPresent',
                    'eas.workingDays',
                    'eas.holidays',
                    'eas.weekOff',
                    'eas.leave',
                    'eas.paidDays',
                    'eas.absent',
                    'eas.month',
                    'eas.year'
                )
                ->where('eas.corpId', $corpId)
                ->where('eas.companyName', $companyName)
                ->where('eas.year', $year)
                ->where('eas.month', $month)
                ->orderBy('eas.empCode')
                ->get();

            if ($attendanceData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No attendance summary found for the specified criteria',
                    'filter' => "corpId: {$corpId}, companyName: {$companyName}, year: {$year}, month: {$month}"
                ], 404);
            }

            // Create a new spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set header row
            $headers = [
                'ID', 'Corp ID', 'Employee Code', 'Employee Name', 'Designation', 'Department', 'Company Name',
                'Total Present', 'Working Days', 'Holidays', 'Week Off', 'Leave', 'Paid Days', 'Absent', 'Month', 'Year'
            ];
            $sheet->fromArray($headers, null, 'A1');

            // Style header row
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ];
            $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

            // Add data rows
            $row = 2;
            foreach ($attendanceData as $data) {
                $sheet->fromArray([
                    $data->id, $data->corpId, $data->empCode, $data->employeeName ?: 'N/A', $data->Designation ?: 'N/A',
                    $data->Department ?: 'N/A', $data->companyName, $data->totalPresent, $data->workingDays,
                    $data->holidays, $data->weekOff, $data->leave, $data->paidDays, $data->absent, $data->month, $data->year
                ], null, 'A' . $row);
                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'P') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Create Excel file and save to a public directory
            $writer = new Xlsx($spreadsheet);
            $filename = "Attendance_Summary_{$corpId}_{$companyName}_{$month}_{$year}.xlsx";
            $exportPath = 'exports';
            $fullPath = storage_path("app/public/{$exportPath}");

            if (!file_exists($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            $filePath = "{$fullPath}/{$filename}";
            $writer->save($filePath);

            // Generate a named route URL for downloading the file
            $downloadUrl = route('attendance-summary.download-file', [
                'corpId' => $corpId,
                'companyName' => urlencode($companyName),
                'year' => $year,
                'month' => $month
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Attendance summary export is ready for download.',
                'data' => [
                    'file_name' => $filename,
                    'download_url' => $downloadUrl,
                    'note' => 'File will be automatically deleted from the server after download.'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error exporting attendance summary (GET): ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while exporting attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download the exported attendance summary file and delete it afterwards.
     *
     * @param string $corpId
     * @param string $companyName
     * @param string $year
     * @param string $month
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadExportedAttendanceSummary($corpId, $companyName, $year, $month)
    {
        try {
            $companyName = urldecode($companyName);
            $filename = "Attendance_Summary_{$corpId}_{$companyName}_{$month}_{$year}.xlsx";
            $filePath = storage_path("app/public/exports/{$filename}");

            if (!file_exists($filePath)) {
                return response()->json([
                    'status' => false,
                    'message' => 'File not found or has already been downloaded and deleted.'
                ], 404);
            }

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error downloading exported file: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while downloading the file.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import and update employee attendance summary from Excel
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function importAttendanceSummary(Request $request)
    {
        // Validate the uploaded file
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');

            // Load the spreadsheet from the uploaded file (without saving it)
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'status' => false,
                    'message' => 'The uploaded file is empty or contains no data rows'
                ], 400);
            }

            // Extract header row and data rows
            $headers = array_shift($rows); // Remove first row (headers)
            
            // Validate headers
            $expectedHeaders = [
                'ID', 'Corp ID', 'Employee Code', 'Employee Name', 'Designation', 
                'Department', 'Company Name', 'Total Present', 'Working Days', 
                'Holidays', 'Week Off', 'Leave', 'Paid Days', 'Absent', 'Month', 'Year'
            ];

            if ($headers !== $expectedHeaders) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid file format. Headers do not match expected format.',
                    'expected_headers' => $expectedHeaders,
                    'received_headers' => $headers
                ], 400);
            }

            $updatedCount = 0;
            $errorCount = 0;
            $errors = [];
            $updatedRecords = [];

            foreach ($rows as $index => $row) {
                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $id = $row[0];
                    $corpId = $row[1];
                    $empCode = $row[2];
                    $companyName = $row[6];
                    $totalPresent = $row[7];
                    $workingDays = $row[8];
                    $holidays = $row[9];
                    $weekOff = $row[10];
                    $leave = $row[11];
                    $paidDays = $row[12];
                    $absent = $row[13];
                    $month = $row[14];
                    $year = $row[15];

                    // Find the attendance summary record
                    $attendanceSummary = EmployeeAttendanceSummary::where('id', $id)
                        ->where('corpId', $corpId)
                        ->where('empCode', $empCode)
                        ->first();

                    if (!$attendanceSummary) {
                        $errorCount++;
                        $errors[] = [
                            'row' => $index + 2, // +2 because we removed header and array is 0-indexed
                            'empCode' => $empCode,
                            'error' => 'Record not found in database'
                        ];
                        continue;
                    }

                    // Update the record
                    $attendanceSummary->update([
                        'totalPresent' => $totalPresent ?? 0,
                        'workingDays' => $workingDays ?? 0,
                        'holidays' => $holidays ?? 0,
                        'weekOff' => $weekOff ?? 0,
                        'leave' => $leave ?? 0,
                        'paidDays' => $paidDays ?? 0,
                        'absent' => $absent ?? 0,
                        'month' => $month,
                        'year' => $year,
                    ]);

                    $updatedCount++;
                    $updatedRecords[] = [
                        'id' => $id,
                        'empCode' => $empCode,
                        'companyName' => $companyName
                    ];

                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'row' => $index + 2,
                        'empCode' => $row[2] ?? 'N/A',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'status' => true,
                'message' => "Successfully updated {$updatedCount} attendance records",
                'summary' => [
                    'total_rows_processed' => count($rows),
                    'successfully_updated' => $updatedCount,
                    'errors' => $errorCount
                ],
                'updated_records' => $updatedRecords,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Error importing attendance summary: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while importing attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
