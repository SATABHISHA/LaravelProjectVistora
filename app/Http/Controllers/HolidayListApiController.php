<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HolidayList;
use Illuminate\Support\Facades\Validator;

class HolidayListApiController extends Controller
{
    /**
     * Add or Update Holiday (same API for both operations)
     */
    public function storeOrUpdate(Request $request)
    {
        try {
            // Validation rules
            $validator = Validator::make($request->all(), [
                'corpId' => 'required|string|max:10',
                'puid' => 'required|string|max:50',
                'companyNames' => 'required|string|max:255',
                'country' => 'required|string|max:50',
                'state' => 'required|string|max:50',
                'city' => 'required|string|max:50',
                'holidayName' => 'required|string|max:255',
                'holidayDate' => 'required|date',
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

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $holiday,
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

            return response()->json([
                'status' => true,
                'message' => 'Holiday fetched successfully',
                'data' => $holiday
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch Holidays by corpId
     */
    public function fetchByCorpId($corpId)
    {
        try {
            $holidays = HolidayList::where('corpId', $corpId)
                ->orderBy('holidayDate', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $holidays,
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
     * Fetch Holidays by corpId and companyNames
     */
    public function fetchByCorpAndCompany($corpId, $companyNames)
    {
        try {
            $holidays = HolidayList::where('corpId', $corpId)
                ->where('companyNames', $companyNames)
                ->orderBy('holidayDate', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $holidays,
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
     * Fetch Holidays by location
     */
    public function fetchByLocation($country, $state = null, $city = null)
    {
        try {
            $query = HolidayList::byLocation($country, $state, $city)
                ->orderBy('holidayDate', 'asc');

            $holidays = $query->get();

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $holidays,
                'count' => $holidays->count(),
                'filters' => [
                    'country' => $country,
                    'state' => $state,
                    'city' => $city
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
     * Fetch Holidays by date range
     */
    public function fetchByDateRange($startDate, $endDate, $corpId = null)
    {
        try {
            $query = HolidayList::byDateRange($startDate, $endDate);

            if ($corpId) {
                $query->where('corpId', $corpId);
            }

            $holidays = $query->orderBy('holidayDate', 'asc')->get();

            return response()->json([
                'status' => true,
                'message' => 'Holidays fetched successfully',
                'data' => $holidays,
                'count' => $holidays->count(),
                'filters' => [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
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
