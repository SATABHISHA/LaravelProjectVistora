<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceApiController extends Controller
{
    /**
     * Handle check-in or check-out
     */
    public function checkInOrOut(Request $request)
    {
        try {
            $request->validate([
                'puid' => 'required|string',
                'corpId' => 'required|string',
                'userName' => 'required|string',
                'empCode' => 'required|string',
                'companyName' => 'required|string',
                'time' => 'required|string', // Time parameter for check-in/out
                'Lat' => 'nullable|string',
                'Long' => 'nullable|string',
                'Address' => 'nullable|string',
            ]);

            // ✅ **FIXED:** Get today's date in the correct timezone
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            
            // Check if user already has an attendance record for today
            $attendance = Attendance::where('puid', $request->puid)
                ->where('date', $today)
                ->first();

            // If no entry exists for today, create a new check-in record
            if (!$attendance) {
                $newAttendance = Attendance::create([
                    'puid' => $request->puid,
                    'corpId' => $request->corpId,
                    'userName' => $request->userName,
                    'empCode' => $request->empCode,
                    'companyName' => $request->companyName,
                    'checkIn' => $request->time,
                    'Lat' => $request->Lat,
                    'Long' => $request->Long,
                    'Address' => $request->Address,
                    'status' => 'IN',
                    'attendanceStatus' => 'Present',
                    'date' => $today
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Check-in successful',
                    'data' => $newAttendance
                ]);
            }
            
            // If user already checked out for today, prevent further actions
            if ($attendance->status === 'OUT') {
                return response()->json([
                    'status' => false,
                    'message' => 'No more check-ins allowed as you have already checked out for today'
                ], 400);
            }
            
            // Handle check-out (when status is "IN")
            if ($attendance->status === 'IN') {
                // ✅ **UPDATED:** Calculate hours between check-in and check-out for AM/PM format
                $totalHours = $this->calculateWorkingHours($attendance->checkIn, $request->time);
                
                // Update the attendance record with checkout time and total hours
                $attendance->update([
                    'checkOut' => $request->time,
                    'totalHrsForTheDay' => $totalHours,
                    'status' => 'OUT',
                    // Location may have changed at checkout
                    'Lat' => $request->Lat ?? $attendance->Lat,
                    'Long' => $request->Long ?? $attendance->Long,
                    'Address' => $request->Address ?? $attendance->Address
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Check-out successful',
                    'data' => $attendance
                ]);
            }
            
            // Fallback response for unexpected status
            return response()->json([
                'status' => false,
                'message' => 'Invalid attendance status'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ **NEW METHOD:** Calculate working hours between check-in and check-out times in AM/PM format
     */
    private function calculateWorkingHours($checkInTime, $checkOutTime)
    {
        try {
            // Parse AM/PM time format
            $checkIn = Carbon::createFromFormat('g:i A', $checkInTime);
            $checkOut = Carbon::createFromFormat('g:i A', $checkOutTime);
            
            // Handle case where checkout is next day (e.g., night shift)
            // If checkout time is earlier than checkin time, assume it's next day
            if ($checkOut->lt($checkIn)) {
                $checkOut->addDay();
            }
            
            // Calculate difference in minutes
            $diffInMinutes = $checkIn->diffInMinutes($checkOut);
            
            // Convert to hours and minutes format
            $hours = floor($diffInMinutes / 60);
            $minutes = $diffInMinutes % 60;
            
            return sprintf('%02d:%02d', $hours, $minutes);
            
        } catch (\Exception $e) {
            // If parsing fails, return 00:00
            return '00:00';
        }
    }

    /**
     * Fetch attendance details for the current day by corpId, userName, empCode, and companyName
     */
    public function fetchTodayAttendance($corpId, $userName, $empCode, $companyName)
    {
        try {
            // ✅ **FIXED:** Get today's date in the correct timezone
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            
            $attendance = Attendance::where('corpId', $corpId)
                ->where('userName', $userName)
                ->where('empCode', $empCode)
                ->where('companyName', $companyName)
                ->where('date', $today)
                ->first();
            
            if (!$attendance) {
                return response()->json([
                    'status' => false,
                    'message' => 'No attendance record found for today with the provided details',
                    'data' => null
                ]);
            }
            
            return response()->json([
                'status' => true,
                'message' => 'Today\'s attendance record retrieved successfully',
                'data' => $attendance
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if attendance exists for today by corpId, empCode, and companyName
     * Returns status true/false with attendance data if exists
     */
    public function checkTodayAttendanceExists($corpId, $empCode, $companyName)
    {
        try {
            // ✅ **FIXED:** Get today's date in the correct timezone
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            
            $attendance = Attendance::where('corpId', $corpId)
                ->where('empCode', $empCode)
                ->where('companyName', $companyName)
                ->where('date', $today)
                ->first();
            
            if (!$attendance) {
                return response()->json([
                    'status' => false,
                    'message' => 'No attendance record found for today',
                    'data' => null
                ]);
            }
            
            return response()->json([
                'status' => true,
                'message' => 'Attendance record exists for today',
                'data' => [
                    'puid' => $attendance->puid,
                    'corpId' => $attendance->corpId,
                    'userName' => $attendance->userName,
                    'empCode' => $attendance->empCode,
                    'companyName' => $attendance->companyName,
                    'checkIn' => $attendance->checkIn,
                    'checkOut' => $attendance->checkOut,
                    'Lat' => $attendance->Lat,
                    'Long' => $attendance->Long,
                    'Address' => $attendance->Address,
                    'totalHrsForTheDay' => $attendance->totalHrsForTheDay,
                    'status' => $attendance->status,
                    'attendanceStatus' => $attendance->attendanceStatus,
                    'date' => $attendance->date,
                    'created_at' => $attendance->created_at,
                    'updated_at' => $attendance->updated_at
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Fetch attendance details grouped by employee for last 5 days, current week, and last 15 days by corpId
     * with optional filter by companyName
     */
    public function fetchAttendanceHistory($corpId, $filter = 'ALL')
    {
        try {
            // Set timezone
            Carbon::setLocale('en');
            $today = Carbon::now('Asia/Kolkata');
            
            // Calculate date ranges
            $currentWeekStart = $today->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            $currentWeekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');
            $last5DaysStart = $today->copy()->subDays(4)->format('Y-m-d'); // Including today, so -4 days
            $last15DaysStart = $today->copy()->subDays(14)->format('Y-m-d'); // Including today, so -14 days
            $todayFormatted = $today->format('Y-m-d');
            
            // Base query
            $query = Attendance::where('corpId', $corpId);
            
            // Apply company filter if not 'ALL'
            if ($filter !== 'ALL' && !empty($filter)) {
                $query->where('companyName', $filter);
            }
            
            // Get all attendance records for the last 15 days (covers all ranges)
            $allAttendance = $query->clone()
                ->whereBetween('date', [$last15DaysStart, $todayFormatted])
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Group by employee (empCode + userName combination)
            $employeeData = [];
            
            foreach ($allAttendance as $attendance) {
                $employeeKey = $attendance->empCode . '_' . $attendance->userName;
                
                if (!isset($employeeData[$employeeKey])) {
                    $employeeData[$employeeKey] = [
                        'puid' => $attendance->puid,
                        'corpId' => $attendance->corpId,
                        'userName' => $attendance->userName,
                        'empCode' => $attendance->empCode,
                        'companyName' => $attendance->companyName,
                        'attendance' => [
                            'last5Days' => [],
                            'currentWeek' => [],
                            'last15Days' => []
                        ]
                    ];
                }
                
                // Parse date and get day name and formatted date
                $attendanceDate = Carbon::parse($attendance->date);
                $dayName = $attendanceDate->format('l'); // Monday, Tuesday, etc.
                $dateName = $attendanceDate->format('jS M'); // 1st Aug, 2nd Sep, etc.
                
                // Create attendance record with only specific fields
                $attendanceRecord = [
                    'checkIn' => $attendance->checkIn,
                    'checkOut' => $attendance->checkOut,
                    'Lat' => $attendance->Lat,
                    'Long' => $attendance->Long,
                    'Address' => $attendance->Address,
                    'totalHrsForTheDay' => $attendance->totalHrsForTheDay,
                    'status' => $attendance->status,
                    'attendanceStatus' => $attendance->attendanceStatus,
                    'date' => $attendance->date,
                    'dayName' => $dayName,
                    'dateName' => $dateName,
                    'created_at' => $attendance->created_at,
                    'updated_at' => $attendance->updated_at
                ];
                
                // ✅ **FIXED:** Add to last15Days first (all records from last 15 days)
                // Since we're already filtering by last 15 days in the query, all records qualify
                $employeeData[$employeeKey]['attendance']['last15Days'][] = $attendanceRecord;
                
                // Add to last5Days if within range
                if ($attendance->date >= $last5DaysStart && $attendance->date <= $todayFormatted) {
                    $employeeData[$employeeKey]['attendance']['last5Days'][] = $attendanceRecord;
                }
                
                // Add to currentWeek if within range
                if ($attendance->date >= $currentWeekStart && $attendance->date <= $currentWeekEnd) {
                    $employeeData[$employeeKey]['attendance']['currentWeek'][] = $attendanceRecord;
                }
            }
            
            // Convert to array and calculate statistics
            $employees = array_values($employeeData);
            $totalEmployees = count($employees);
            $totalRecords = $allAttendance->count();
            
            // Calculate statistics per time range
            $last5DaysTotal = 0;
            $currentWeekTotal = 0;
            $last15DaysTotal = $totalRecords;
            
            foreach ($employees as &$employee) {
                $last5DaysTotal += count($employee['attendance']['last5Days']);
                $currentWeekTotal += count($employee['attendance']['currentWeek']);
                
                // Add counts to each employee data
                $employee['statistics'] = [
                    'last5DaysCount' => count($employee['attendance']['last5Days']),
                    'currentWeekCount' => count($employee['attendance']['currentWeek']),
                    'last15DaysCount' => count($employee['attendance']['last15Days'])
                ];
            }
            
            // Get unique company names for filter options
            $availableCompanies = Attendance::where('corpId', $corpId)
                ->distinct()
                ->pluck('companyName')
                ->filter()
                ->values()
                ->toArray();
            
            return response()->json([
                'status' => true,
                'message' => 'Employee attendance history retrieved successfully',
                'data' => [
                    'corpId' => $corpId,
                    'filter' => $filter,
                    'dateRanges' => [
                        'currentWeek' => [
                            'start' => $currentWeekStart,
                            'end' => $currentWeekEnd
                        ],
                        'last5Days' => [
                            'start' => $last5DaysStart,
                            'end' => $todayFormatted
                        ],
                        'last15Days' => [
                            'start' => $last15DaysStart,
                            'end' => $todayFormatted
                        ]
                    ],
                    'employees' => $employees,
                    'statistics' => [
                        'totalEmployees' => $totalEmployees,
                        'totalRecords' => $totalRecords,
                        'last5DaysTotal' => $last5DaysTotal,
                        'currentWeekTotal' => $currentWeekTotal,
                        'last15DaysTotal' => $last15DaysTotal
                    ],
                    'filterOptions' => [
                        'availableCompanies' => array_merge(['ALL'], $availableCompanies),
                        'currentFilter' => $filter
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Bulk insert attendance records for testing purposes
     */
    public function bulkInsertAttendance(Request $request)
    {
        try {
            $request->validate([
                'records' => 'required|array',
                'records.*.corpId' => 'required|string',
                'records.*.userName' => 'required|string',
                'records.*.empCode' => 'required|string',
                'records.*.companyName' => 'required|string',
                'records.*.checkIn' => 'nullable|string',
                'records.*.checkOut' => 'nullable|string',
                'records.*.date' => 'required|string',
                'records.*.Lat' => 'nullable|string',
                'records.*.Long' => 'nullable|string',
                'records.*.Address' => 'nullable|string',
            ]);

            $insertedRecords = [];

            foreach ($request->records as $record) {
                // Check if record already exists for this date
                $existingRecord = Attendance::where('corpId', $record['corpId'])
                    ->where('empCode', $record['empCode'])
                    ->where('date', $record['date'])
                    ->first();

                if (!$existingRecord) {
                    // Generate unique PUID for each record
                    $uniquePuid = uniqid($record['empCode'] . '_' . str_replace('-', '', $record['date']) . '_', true);
                    
                    $attendance = Attendance::create([
                        'puid' => $uniquePuid,
                        'corpId' => $record['corpId'],
                        'userName' => $record['userName'],
                        'empCode' => $record['empCode'],
                        'companyName' => $record['companyName'],
                        'checkIn' => $record['checkIn'] ?? null,
                        'checkOut' => $record['checkOut'] ?? null,
                        'Lat' => $record['Lat'] ?? null,
                        'Long' => $record['Long'] ?? null,
                        'Address' => $record['Address'] ?? null,
                        'totalHrsForTheDay' => isset($record['checkIn']) && isset($record['checkOut']) 
                            ? $this->calculateWorkingHours($record['checkIn'], $record['checkOut']) 
                            : null,
                        'status' => isset($record['checkOut']) ? 'OUT' : 'IN',
                        'attendanceStatus' => 'Present',
                        'date' => $record['date']
                    ]);

                    $insertedRecords[] = $attendance;
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Bulk attendance records inserted successfully',
                'data' => [
                    'insertedCount' => count($insertedRecords),
                    'records' => $insertedRecords
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch monthly attendance details with pagination
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchMonthlyAttendance(Request $request)
    {
        try {
            $request->validate([
                'corpId' => 'required|string',
                'userName' => 'nullable|string',
                'empCode' => 'nullable|string',
                'companyName' => 'nullable|string',
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            // Set default values for month and year if not provided
            $month = $request->month ?: Carbon::now('Asia/Kolkata')->month;
            $year = $request->year ?: Carbon::now('Asia/Kolkata')->year;
            
            // Create Carbon instance for the requested month
            $date = Carbon::createFromDate($year, $month, 1, 'Asia/Kolkata');
            
            // Get month name with year
            $monthName = $date->format('F Y');
            
            // Calculate days in month
            $daysInMonth = $date->daysInMonth;
            
            // Filter by date range for the requested month
            $startDate = $date->format('Y-m-d');
            $endDate = $date->copy()->endOfMonth()->format('Y-m-d');
            
            // Get holidays for this month, corp and company
            $holidayQuery = \App\Models\HolidayList::where('corpId', $request->corpId)
                ->where('year', $year)
                ->whereBetween('holidayDate', [$startDate, $endDate]);
                
            // Apply company filter if provided
            if ($request->companyName) {
                // Check if the holiday applies to this company
                // This handles both exact matches and comma-separated lists of companies
                $holidayQuery->where(function($query) use ($request) {
                    $query->where('companyNames', $request->companyName)
                          ->orWhere('companyNames', 'like', $request->companyName . ',%')
                          ->orWhere('companyNames', 'like', '%,' . $request->companyName . ',%')
                          ->orWhere('companyNames', 'like', '%,' . $request->companyName);
                });
            }
            
            $holidays = $holidayQuery->get();
            
            // Create a map of holidays by date for quick lookup
            $holidaysByDate = [];
            foreach ($holidays as $holiday) {
                $holidayDate = Carbon::parse($holiday->holidayDate)->format('Y-m-d');
                $holidaysByDate[$holidayDate] = $holiday;
            }
            
            // Build attendance query
            $query = Attendance::where('corpId', $request->corpId);
            
            // Add optional filters
            if ($request->userName) {
                $query->where('userName', $request->userName);
            }
            
            if ($request->empCode) {
                $query->where('empCode', $request->empCode);
            }
            
            if ($request->companyName) {
                $query->where('companyName', $request->companyName);
            }
            
            // Filter by date range for the requested month
            $query->whereBetween('date', [$startDate, $endDate]);
            
            // Get attendance records
            $attendanceRecords = $query->orderBy('date', 'asc')->get();
            
            // Create calendar array with all days in month
            $calendar = [];
            
            // Prepare attendance data by date
            $attendanceByDate = [];
            foreach ($attendanceRecords as $record) {
                $recordDate = Carbon::parse($record->date);
                $dateKey = $recordDate->format('Y-m-d');
                $attendanceByDate[$dateKey] = [
                    'id' => $record->id,
                    'puid' => $record->puid,
                    'checkIn' => $record->checkIn,
                    'checkOut' => $record->checkOut,
                    'status' => $record->status,
                    'attendanceStatus' => $record->attendanceStatus,
                    'totalHrsForTheDay' => $record->totalHrsForTheDay,
                    'dayName' => $recordDate->format('D'), // Short day name (Mon, Tue, etc.)
                    'dateOnly' => $recordDate->format('d'), // Day number only (01-31)
                    'fullDate' => $recordDate->format('Y-m-d'),
                    'Lat' => $record->Lat,
                    'Long' => $record->Long,
                    'Address' => $record->Address
                ];
            }
            
            // Fill calendar array with all days in month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $currentDate = Carbon::createFromDate($year, $month, $day, 'Asia/Kolkata');
                $dateKey = $currentDate->format('Y-m-d');
                
                if (isset($attendanceByDate[$dateKey])) {
                    // Day has attendance record
                    $calendar[] = $attendanceByDate[$dateKey];
                } else {
                    // Check if this day is a holiday
                    $isHoliday = isset($holidaysByDate[$dateKey]);
                    
                    // Day without attendance record
                    $calendarEntry = [
                        'id' => null,
                        'puid' => null,
                        'checkIn' => null,
                        'checkOut' => null,
                        'status' => $isHoliday ? 'HOLIDAY' : 'ABSENT',
                        'attendanceStatus' => $isHoliday ? 'Holiday' : 'Absent',
                        'totalHrsForTheDay' => '00:00',
                        'dayName' => $currentDate->format('D'), // Short day name
                        'dateOnly' => $currentDate->format('d'), // Day number
                        'fullDate' => $dateKey,
                        'Lat' => null,
                        'Long' => null,
                        'Address' => null
                    ];
                    
                    // Add holiday information if applicable
                    if ($isHoliday) {
                        $holiday = $holidaysByDate[$dateKey];
                        $calendarEntry['holidayName'] = $holiday->holidayName;
                        $calendarEntry['holidayType'] = $holiday->holidayType;
                    }
                    
                    $calendar[] = $calendarEntry;
                }
            }
            
            // Get previous and next month info for pagination
            $prevMonth = $date->copy()->subMonth();
            $nextMonth = $date->copy()->addMonth();
            
            return response()->json([
                'status' => true,
                'message' => 'Monthly attendance records retrieved successfully',
                'data' => [
                    'corpId' => $request->corpId,
                    'userName' => $request->userName,
                    'empCode' => $request->empCode,
                    'companyName' => $request->companyName,
                    'monthName' => $monthName,
                    'year' => $year,
                    'month' => $month,
                    'daysInMonth' => $daysInMonth,
                    'attendance' => $calendar,
                    'pagination' => [
                        'prevMonth' => $prevMonth->month,
                        'prevYear' => $prevMonth->year,
                        'prevMonthName' => $prevMonth->format('F Y'),
                        'nextMonth' => $nextMonth->month,
                        'nextYear' => $nextMonth->year,
                        'nextMonthName' => $nextMonth->format('F Y')
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current month and year
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentMonthYear()
    {
        try {
            $now = Carbon::now('Asia/Kolkata');
            
            return response()->json([
                'status' => true,
                'message' => 'Current month and year retrieved successfully',
                'data' => [
                    'month' => (int) $now->format('n'), // Month without leading zeros (1-12)
                    'year' => (int) $now->format('Y'),  // 4-digit year
                    'monthName' => $now->format('F Y')  // Full month name and year (e.g. "September 2025")
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch today's attendance with calculated hours
     * Returns N/A for missing check-in/out times and calculates hours based on different scenarios
     */
    public function fetchTodayAttendanceWithHours($empCode, $corpId, $companyName)
    {
        try {
            // Get today's date in the correct timezone
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            $currentTime = Carbon::now('Asia/Kolkata');
            
            // Find today's attendance record
            $attendance = Attendance::where('empCode', $empCode)
                ->where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('date', $today)
                ->first();
            
            // Initialize response data
            $responseData = [
                'empCode' => $empCode,
                'corpId' => $corpId,
                'companyName' => $companyName,
                'date' => $today,
                'currentTime' => $currentTime->format('g:i A'),
                'checkIn' => 'N/A',
                'checkOut' => 'N/A',
                'totalHours' => '0.0',
                'status' => 'No Record',
                'attendanceStatus' => 'Absent'
            ];
            
            if (!$attendance) {
                // No attendance record found for today
                return response()->json([
                    'status' => true,
                    'message' => 'No attendance record found for today',
                    'data' => $responseData
                ]);
            }
            
            // Update response data with found attendance
            $responseData['checkIn'] = $attendance->checkIn ?? 'N/A';
            $responseData['checkOut'] = $attendance->checkOut ?? 'N/A';
            $responseData['status'] = $attendance->status ?? 'No Record';
            $responseData['attendanceStatus'] = $attendance->attendanceStatus ?? 'Absent';
            $responseData['puid'] = $attendance->puid;
            $responseData['userName'] = $attendance->userName;
            
            // Calculate hours based on different scenarios
            if ($attendance->checkIn && $attendance->checkOut) {
                // Scenario 1: Both check-in and check-out done
                $responseData['totalHours'] = $this->calculateWorkingHours($attendance->checkIn, $attendance->checkOut);
                $responseData['hoursType'] = 'Completed Shift';
                
            } elseif ($attendance->checkIn && !$attendance->checkOut) {
                // Scenario 2: Only check-in done, calculate hours till current time
                $responseData['totalHours'] = $this->calculateWorkingHours($attendance->checkIn, $currentTime->format('g:i A'));
                $responseData['hoursType'] = 'Ongoing Shift';
                
            } else {
                // Scenario 3: Not yet checked in
                $responseData['totalHours'] = '0.0';
                $responseData['hoursType'] = 'Not Started';
            }
            
            return response()->json([
                'status' => true,
                'message' => 'Today\'s attendance retrieved successfully',
                'data' => $responseData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
