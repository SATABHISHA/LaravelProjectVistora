<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeAttendanceSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
            $startDate = Carbon::create($year, $monthNumber, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $monthNumber, 1)->endOfMonth()->format('Y-m-d');
            
            $leaveRequests = DB::table('leave_request')
                ->select('empcode', 'from_date', 'to_date', 'status')
                ->where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('from_date', [$startDate, $endDate])
                          ->orWhereBetween('to_date', [$startDate, $endDate])
                          ->orWhere(function($q) use ($startDate, $endDate) {
                              $q->where('from_date', '<=', $startDate)
                                ->where('to_date', '>=', $endDate);
                          });
                })
                ->get();
            
            // Create a map of leave status by empCode and date
            $leaveStatusMap = [];
            foreach ($leaveRequests as $leave) {
                $fromDate = Carbon::parse($leave->from_date);
                $toDate = Carbon::parse($leave->to_date);
                
                for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                    $dateKey = $date->format('Y-m-d');
                    if ($date->gte(Carbon::parse($startDate)) && $date->lte(Carbon::parse($endDate))) {
                        $leaveStatusMap[$leave->empcode][$dateKey] = $leave->status;
                    }
                }
            }

            // Get unique employee codes from attendance data
            $empCodes = $attendanceData->pluck('empCode')->unique();

            $summaryData = [];
            $processedCount = 0;

            foreach ($empCodes as $empCode) {
                // Calculate attendance statistics for this employee
                $employeeAttendance = $attendanceData->where('empCode', $empCode);
                
                $totalPresent = 0;
                $totalLeave = 0;
                $totalAbsent = 0;
                
                foreach ($employeeAttendance as $attendance) {
                    $attendanceDate = Carbon::parse($attendance->date)->format('Y-m-d');
                    $leaveStatus = $leaveStatusMap[$empCode][$attendanceDate] ?? null;
                    
                    // Determine the status based on leave request
                    if ($leaveStatus === 'Approved') {
                        // Approved leave counts as Leave
                        $totalLeave++;
                    } elseif (in_array($leaveStatus, ['Pending', 'Rejected', 'Returned'])) {
                        // Pending/Rejected/Returned leave counts as Absent
                        $totalAbsent++;
                    } elseif ($attendance->attendanceStatus === 'Present') {
                        // No leave request or attendance is Present
                        $totalPresent++;
                    } elseif ($attendance->attendanceStatus === 'Leave') {
                        // Direct leave entry in attendance
                        $totalLeave++;
                    } else {
                        // Other cases count as Absent
                        $totalAbsent++;
                    }
                }
                
                // Calculate working days (total days in month - holidays - week-offs)
                $totalDaysInMonth = Carbon::createFromFormat('F Y', $month . ' ' . $year)->daysInMonth;
                $workingDays = $totalDaysInMonth - $holidays - $weekOffCount;
                
                // Calculate paid days: working days - absent
                $paidDays = $workingDays - $totalAbsent;

                $summaryData[] = [
                    'corpId' => $corpId,
                    'empCode' => $empCode,
                    'companyName' => $companyName,
                    'totalPresent' => $totalPresent,
                    'workingDays' => $workingDays,
                    'holidays' => $holidays,
                    'weekOff' => $weekOffCount,
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
                    'holidays_in_period' => $holidays,
                    'week_off_days' => $weekOffCount,
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
            $startDate = Carbon::create($year, $monthNumber, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $monthNumber, 1)->endOfMonth()->format('Y-m-d');
            
            $leaveRequests = DB::table('leave_request')
                ->select('empcode', 'from_date', 'to_date', 'status')
                ->where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('from_date', [$startDate, $endDate])
                          ->orWhereBetween('to_date', [$startDate, $endDate])
                          ->orWhere(function($q) use ($startDate, $endDate) {
                              $q->where('from_date', '<=', $startDate)
                                ->where('to_date', '>=', $endDate);
                          });
                })
                ->get();
            
            // Create a map of leave status by empCode and date
            $leaveStatusMap = [];
            foreach ($leaveRequests as $leave) {
                $fromDate = Carbon::parse($leave->from_date);
                $toDate = Carbon::parse($leave->to_date);
                
                for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                    $dateKey = $date->format('Y-m-d');
                    if ($date->gte(Carbon::parse($startDate)) && $date->lte(Carbon::parse($endDate))) {
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
                $totalLeave = 0;
                
                foreach ($employeeAttendance as $attendance) {
                    $attendanceDate = Carbon::parse($attendance->date)->format('Y-m-d');

                    // Skip non-working days from any calculation
                    if (in_array($attendanceDate, $nonWorkingDates)) {
                        continue;
                    }

                    $leaveStatus = $leaveStatusMap[$empCode][$attendanceDate] ?? null;
                    
                    if ($leaveStatus === 'Approved') {
                        $totalLeave++;
                    } elseif ($attendance->attendanceStatus === 'Present') {
                        $totalPresent++;
                    }
                }
                
                // Correctly calculate absent and paid days
                $totalAbsent = $workingDays - $totalPresent - $totalLeave;
                $paidDays = $totalPresent + $totalLeave;

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
            ->get(); // Fetches all week rows for the policy

        $scheduleMap = [];
        foreach ($weeklySchedule as $week) {
            $scheduleMap[$week->week_no] = (array)$week;
        }

        for ($date = $firstDay->copy(); $date->lte($lastDay); $date->addDay()) {
            $weekOfMonth = 'Week ' . $date->weekOfMonth;
            $dayName = strtolower($date->format('l')); // sunday, monday, etc.

            if (isset($scheduleMap[$weekOfMonth])) {
                $weekSchedule = $scheduleMap[$weekOfMonth];
                // Check if the day is a full day off
                if (isset($weekSchedule[$dayName]) && $weekSchedule[$dayName] === 'Full Day') {
                    $dates[] = $date->format('Y-m-d');
                }
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
            $startDate = Carbon::create($year, $monthNumber, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $monthNumber, 1)->endOfMonth()->format('Y-m-d');
            
            $leaveRequests = DB::table('leave_request')
                ->where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('from_date', [$startDate, $endDate])
                          ->orWhereBetween('to_date', [$startDate, $endDate])
                          ->orWhere(function($q) use ($startDate, $endDate) {
                              $q->where('from_date', '<=', $startDate)->where('to_date', '>=', $endDate);
                          });
                })
                ->get();

            $leaveStatusMap = [];
            foreach ($leaveRequests as $leave) {
                for ($date = Carbon::parse($leave->from_date); $date->lte(Carbon::parse($leave->to_date)); $date->addDay()) {
                    $dateKey = $date->format('Y-m-d');
                    if ($date->gte(Carbon::parse($startDate)) && $date->lte(Carbon::parse($endDate))) {
                        $leaveStatusMap[$leave->empcode][$dateKey] = $leave->status;
                    }
                }
            }

            $updatedCount = 0;
            foreach ($existingSummaries as $summary) {
                $empCode = $summary->empCode;
                $employeeAttendance = $attendanceData->get($empCode) ?? collect();

                $totalPresent = 0;
                $totalLeave = 0;

                foreach ($employeeAttendance as $attendance) {
                    $attendanceDate = Carbon::parse($attendance->date)->format('Y-m-d');
                    if (in_array($attendanceDate, $nonWorkingDates)) continue;

                    $leaveStatus = $leaveStatusMap[$empCode][$attendanceDate] ?? null;
                    
                    if ($leaveStatus === 'Approved') {
                        $totalLeave++;
                    } elseif ($attendance->attendanceStatus === 'Present') {
                        $totalPresent++;
                    }
                }
                
                $totalAbsent = $workingDays - $totalPresent - $totalLeave;
                $paidDays = $totalPresent + $totalLeave;

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
            \Log::error("Recalculation Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'An error occurred during recalculation.', 'error' => $e->getMessage()], 500);
        }
    }
}
