<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class UtilityApiController extends Controller
{
    /**
     * Get a list of years including current year and previous years
     * 
     * @param int $count Number of previous years to include (default: 10)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getYearsList($count = 10)
    {
        $currentYear = date('Y');
        $years = [];
        
        // Add current year with a flag
        $years[] = [
            'year' => (int)$currentYear,
            'isCurrentYear' => true,
        ];
        
        // Add previous years
        for ($i = 1; $i <= $count; $i++) {
            $year = $currentYear - $i;
            $years[] = [
                'year' => (int)$year,
                'isCurrentYear' => false,
            ];
        }
        
        return response()->json([
            'data' => $years,
            'currentYear' => (int)$currentYear
        ]);
    }

    /**
     * Return current date and time in specified formats
     * GET /current-datetime
     * date => YYYY-MM-DD
     * time => HH:MM:SS
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function currentDateTime(Request $request)
    {
        try {
            // Use optional timezone query param, otherwise prefer app timezone.
            // If app timezone is UTC (default) fall back to PHP default timezone.
            $tz = $request->query('timezone');
            if (empty($tz)) {
                $appTz = Config::get('app.timezone');
                if (!empty($appTz) && strtolower($appTz) !== 'utc') {
                    $tz = $appTz;
                } else {
                    $tz = date_default_timezone_get();
                }
            }

            // Validate timezone string (IANA) and return clear error if invalid
            if (!in_array($tz, timezone_identifiers_list())) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid timezone provided',
                    'timezone' => $tz
                ], 422);
            }

            // Create Carbon instance in requested timezone
            $now = Carbon::now($tz);

            $date = $now->format('Y-m-d');
            $time = $now->format('H:i:s');

            return response()->json([
                'status' => true,
                'date' => $date,
                'time' => $time,
                'timezone' => $tz,
                'timestamp' => $now->timestamp
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Could not get current date/time',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
