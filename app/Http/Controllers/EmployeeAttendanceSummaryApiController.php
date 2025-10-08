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

            // Get unique employee codes from attendance data
            $empCodes = $attendanceData->pluck('empCode')->unique();

            $summaryData = [];
            $processedCount = 0;

            foreach ($empCodes as $empCode) {
                // Calculate attendance statistics for this employee
                $employeeAttendance = $attendanceData->where('empCode', $empCode);
                
                $totalPresent = $employeeAttendance->where('attendanceStatus', 'Present')->count();
                $totalAbsent = $employeeAttendance->where('attendanceStatus', 'Absent')->count();
                
                // Calculate working days (total days in month - holidays - week-offs)
                $totalDaysInMonth = Carbon::createFromFormat('F Y', $month . ' ' . $year)->daysInMonth;
                $workingDays = $totalDaysInMonth - $holidays - $weekOffCount;

                $summaryData[] = [
                    'corpId' => $corpId,
                    'empCode' => $empCode,
                    'companyName' => $companyName,
                    'totalPresent' => $totalPresent,
                    'workingDays' => $workingDays,
                    'holidays' => $holidays,
                    'weekOff' => $weekOffCount,
                    'leave' => $totalAbsent, // Absent days count as leave
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
            $query = EmployeeAttendanceSummary::query();

            // Apply filters if provided
            if ($request->has('corpId')) {
                $query->where('corpId', $request->corpId);
            }

            if ($request->has('companyName')) {
                $query->where('companyName', $request->companyName);
            }

            if ($request->has('empCode')) {
                $query->where('empCode', $request->empCode);
            }

            if ($request->has('month')) {
                $query->where('month', $request->month);
            }

            if ($request->has('year')) {
                $query->where('year', $request->year);
            }

            // Get paginated results
            $perPage = $request->input('per_page', 15);
            $attendanceSummaries = $query->orderBy('created_at', 'desc')->paginate($perPage);

            if ($attendanceSummaries->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No attendance summary records found',
                    'data' => []
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Attendance summaries retrieved successfully',
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
}
