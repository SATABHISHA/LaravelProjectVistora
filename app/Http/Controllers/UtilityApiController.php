<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
