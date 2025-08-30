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
            $today = Carbon::now()->format('Y-m-d');
            
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
                // Calculate hours between check-in and check-out
                $checkInTime = Carbon::parse($attendance->checkIn);
                $checkOutTime = Carbon::parse($request->time);
                
                // Calculate difference in hours and minutes
                $diffInMinutes = $checkInTime->diffInMinutes($checkOutTime);
                $hours = floor($diffInMinutes / 60);
                $minutes = $diffInMinutes % 60;
                $totalHours = sprintf('%02d:%02d', $hours, $minutes);
                
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
     * Fetch attendance details for the current day by corpId, userName, empCode, and companyName
     */
    public function fetchTodayAttendance($corpId, $userName, $empCode, $companyName)
    {
        try {
            // ✅ **FIXED:** Get today's date in the correct timezone
            $today = Carbon::now()->format('Y-m-d');
            
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
            $today = Carbon::now()->format('Y-m-d');
            
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
}
