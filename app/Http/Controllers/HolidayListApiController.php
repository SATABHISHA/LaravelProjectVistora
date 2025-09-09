<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HolidayList;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HolidayListApiController extends Controller
{
    /**
     * Helper method to format date as "1st Nov" format
     */
    private function formatHolidayDate($date)
    {
        if (!$date) return null;
        
        try {
            $carbon = Carbon::parse($date);
            $day = $carbon->day;
            $month = $carbon->format('M'); // Short month name (Jan, Feb, etc.)
            
            // Add ordinal suffix to day
            $suffix = '';
            if ($day % 10 == 1 && $day != 11) {
                $suffix = 'st';
            } elseif ($day % 10 == 2 && $day != 12) {
                $suffix = 'nd';
            } elseif ($day % 10 == 3 && $day != 13) {
                $suffix = 'rd';
            } else {
                $suffix = 'th';
            }
            
            return $day . $suffix . ' ' . $month;
        } catch (\Exception $e) {
            return $date; // Return original if parsing fails
        }
    }

    /**
     * Helper method to format created_at date as "Jan 15, 2025"
     */
    private function formatCreatedDate($date)
    {
        if (!$date) return null;
        
        try {
            return Carbon::parse($date)->format('M j, Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Helper method to format holiday data with formatted date
     */
    private function formatHolidayData($holiday)
    {
        if (is_array($holiday)) {
            $holiday['holidayDateFormatted'] = $this->formatHolidayDate($holiday['holidayDate']);
        } else {
            $holidayArray = $holiday->toArray();
            $holidayArray['holidayDateFormatted'] = $this->formatHolidayDate($holiday->holidayDate);
            return $holidayArray;
        }
        
        return $holiday;
    }

    /**
     * Helper method to format collection of holidays
     */
    private function formatHolidaysCollection($holidays)
    {
        return $holidays->map(function ($holiday) {
            return $this->formatHolidayData($holiday);
        });
    }

    /**
     * Add or Update Holiday (same API for both operations)
     */
    public function storeOrUpdate(Request $request)
    {
        try {
            // Validation rules with year field
            $validator = Validator::make($request->all(), [
                'corpId' => 'required|string|max:10',
                'puid' => 'required|string|max:50',
                'companyNames' => 'required|string|max:255',
                'country' => 'required|string|max:50',
                'state' => 'required|string|max:50',
                'city' => 'required|string|max:50',
                'holidayName' => 'required|string|max:255',
                'holidayDate' => 'required|date',
                'year' => 'required|string|max:4',
                'holidayType' => 'required|string|max:100',
                'recurringType' => 'required|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();

            // Check if holiday exists by puid
            $holiday = HolidayList::where('puid', $data['puid'])->first();

            if ($holiday) {
                // Update existing holiday
                $holiday->update($data);
                $message = 'Holiday updated successfully';
                $status = 'updated';
            } else {
                // Create new holiday
                $holiday = HolidayList::create($data);
                $message = 'Holiday created successfully';
                $status = 'created';
            }

            // Format the response data
            $formattedHoliday = $this->formatHolidayData($holiday);

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $formattedHoliday,
                'operation' => $status
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Holiday by puid
     */
    public function destroy($puid)
    {
        try {
            $holiday = HolidayList::where('puid', $puid)->first();

            if (!$holiday) {
                return response()->json([
                    'status' => false,
                    'message' => 'Holiday not found'
                ], 404);
            }

            $holiday->delete();

            return response()->json([
                'status' => true,
                'message' => 'Holiday deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch Holiday by puid
     */
    public function fetchByPuid($puid)
    {
        try {
            $holiday = HolidayList::where('puid', $puid)->first();

            if (!$holiday) {
                return response()->json([
                    'status' => false,
                    'message' => 'Holiday not found',
                    'data' => (object)[]
                ], 404);
            }

            // Format the holiday data
            $formattedHoliday = $this->formatHolidayData($holiday);

            return response()->json([
                'status' => true,
                'message' => 'Holiday fetched successfully',
                'data' => $formattedHoliday
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ UPDATED: Fetch Holidays by corpId - Grouped by Year first, then by Company
     */
    public function fetchByCorpId($corpId)
    {
        try {
            $holidays = HolidayList::where('corpId', $corpId)
                ->orderBy('year', 'desc')
                ->orderBy('companyNames', 'asc')
                ->orderBy('holidayDate', 'asc')
                ->get();

            // First group by year
            $groupedByYear = $holidays->groupBy('year');

            // Format the grouped data
            $formattedData = [];
            $totalHolidays = 0;
            $totalYears = 0;

            foreach ($groupedByYear as $year => $yearHolidays) {
                $totalYears++;
                
                // Group year holidays by company
                $groupedByCompany = $yearHolidays->groupBy('companyNames');
                
                $companyData = [];
                $yearTotalHolidays = 0;
                
                foreach ($groupedByCompany as $companyName => $companyHolidays) {
                    $formattedCompanyHolidays = $this->formatHolidaysCollection($companyHolidays);
                    
                    // Get the most recent created_at date for this company in this year
                    $latestCreatedAt = $companyHolidays->max('created_at');
                    
                    $companyData[] = [
                        'companyName' => $companyName,
                        'year' => $year, // ✅ Added year inside company
                        'totalHolidays' => $companyHolidays->count(),
                        'createdAt' => $this->formatCreatedDate($latestCreatedAt), // ✅ Added createdAt inside company
                        'holidays' => $formattedCompanyHolidays
                    ];
                    
                    $yearTotalHolidays += $companyHolidays->count();
                }
                
                $formattedData[] = [
                    'year' => $year,
                    'totalHolidays' => $yearTotalHolidays,
                    'totalCompanies' => count($companyData),
                    'companies' => $companyData
                ];

                $totalHolidays += $yearTotalHolidays;
            }

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $formattedData,
                'totalYears' => $totalYears,
                'totalHolidays' => $totalHolidays
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch Holidays by corpId and companyNames
     */
    public function fetchByCorpAndCompany($corpId, $companyNames)
    {
        try {
            $holidays = HolidayList::where('corpId', $corpId)
                ->where('companyNames', $companyNames)
                ->orderBy('year', 'desc')
                ->orderBy('holidayDate', 'asc')
                ->get();

            // Format the holidays collection
            $formattedHolidays = $this->formatHolidaysCollection($holidays);

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $formattedHolidays,
                'count' => $holidays->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch Holidays by location with optional year filter
     */
    public function fetchByLocation($country, $state = null, $city = null, $year = null)
    {
        try {
            $query = HolidayList::byLocation($country, $state, $city);
            
            if ($year) {
                $query->byYear($year);
            }
            
            $holidays = $query->orderBy('year', 'desc')
                            ->orderBy('holidayDate', 'asc')
                            ->get();

            // Format the holidays collection
            $formattedHolidays = $this->formatHolidaysCollection($holidays);

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $formattedHolidays,
                'count' => $holidays->count(),
                'filters' => [
                    'country' => $country,
                    'state' => $state,
                    'city' => $city,
                    'year' => $year
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch Holidays by date range with optional year filter
     */
    public function fetchByDateRange($startDate, $endDate, $corpId = null, $year = null)
    {
        try {
            $query = HolidayList::byDateRange($startDate, $endDate);

            if ($corpId) {
                $query->where('corpId', $corpId);
            }
            
            if ($year) {
                $query->byYear($year);
            }

            $holidays = $query->orderBy('year', 'desc')
                            ->orderBy('holidayDate', 'asc')
                            ->get();

            // Format the holidays collection
            $formattedHolidays = $this->formatHolidaysCollection($holidays);

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $formattedHolidays,
                'count' => $holidays->count(),
                'filters' => [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'corpId' => $corpId,
                    'year' => $year
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch Holidays by Year
     */
    public function fetchByYear($year, $corpId = null)
    {
        try {
            $query = HolidayList::byYear($year);
            
            if ($corpId) {
                $query->where('corpId', $corpId);
            }

            $holidays = $query->orderBy('holidayDate', 'asc')->get();

            // Format the holidays collection
            $formattedHolidays = $this->formatHolidaysCollection($holidays);

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $formattedHolidays,
                'count' => $holidays->count(),
                'filters' => [
                    'year' => $year,
                    'corpId' => $corpId
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
