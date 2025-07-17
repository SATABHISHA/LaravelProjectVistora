<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShiftPolicyWeeklySchedule;

class ShiftPolicyWeeklyScheduleApiController extends Controller
{
    // Add schedule (no duplicate day_name for week_no and puid)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'puid' => 'required|string',
            'week_no' => 'required|string',
            'day_name' => 'required|string',
            'time' => 'required|string',
        ]);

        $exists = ShiftPolicyWeeklySchedule::where('puid', $request->puid)
            ->where('week_no', $request->week_no)
            ->where('day_name', $request->day_name)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Duplicate day_name for this week_no and puid.'
            ], 409);
        }

        $schedule = ShiftPolicyWeeklySchedule::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Schedule added successfully.',
            'data' => $schedule
        ], 201);
    }

    // Update by puid and id
    public function update(Request $request, $puid, $id)
    {
        $schedule = ShiftPolicyWeeklySchedule::where('puid', $puid)->where('id', $id)->first();
        if (!$schedule) {
            return response()->json([
                'status' => false,
                'message' => 'Schedule not found.'
            ], 404);
        }

        // Prevent duplicate day_name for week_no and puid on update
        if ($request->week_no && $request->day_name) {
            $exists = ShiftPolicyWeeklySchedule::where('puid', $puid)
                ->where('week_no', $request->week_no)
                ->where('day_name', $request->day_name)
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Duplicate day_name for this week_no and puid.'
                ], 409);
            }
        }

        $schedule->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Schedule updated successfully.',
            'data' => $schedule
        ]);
    }

    // Delete all by puid
    public function destroyByPuid($puid)
    {
        $deleted = ShiftPolicyWeeklySchedule::where('puid', $puid)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'All schedules deleted for this puid.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No schedules found for this puid.'
            ], 404);
        }
    }

    // Delete by puid and id
    public function destroyByPuidAndId($puid, $id)
    {
        $deleted = ShiftPolicyWeeklySchedule::where('puid', $puid)->where('id', $id)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Schedule deleted for this puid and id.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No schedule found for this puid and id.'
            ], 404);
        }
    }

    // Fetch by puid, group by week_no, add color
    public function fetchByPuid($puid)
    {
        $schedules = ShiftPolicyWeeklySchedule::where('puid', $puid)->get();

        $grouped = $schedules->groupBy('week_no');
        $data = [];

        foreach ($grouped as $week_no => $items) {
            // Generate a random light color hex code
            $color = sprintf("#%02X%02X%02X", rand(180,255), rand(180,255), rand(180,255));
            $data[] = [
                'week_no' => $week_no,
                'color' => $color,
                'weekoffarr' => $items->values()
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Data fetched successfully.',
            'data' => $data
        ]);
    }
}
