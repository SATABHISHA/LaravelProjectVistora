<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NewsFeed;
use App\Models\NewsFeedReview;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NewsFeedController extends Controller
{
    /**
     * Store a new news feed entry
     * POST /newsfeed
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'EmpCode' => 'required|string|max:20',
            'companyName' => 'required|string|max:100',
            'body' => 'required|string',
            'date' => 'required|string|max:20',
            'time' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Fetch employee details from employee_details table
            $employee = \DB::table('employee_details')
                ->where('corp_id', $request->corpId)
                ->where('EmpCode', $request->EmpCode)
                ->first();

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found with the provided EmpCode',
                    'corpId' => $request->corpId,
                    'EmpCode' => $request->EmpCode
                ], 404);
            }

            // Concatenate employee name
            $employeeFullName = trim(
                ($employee->FirstName ?? '') . ' ' . 
                ($employee->MiddleName ?? '') . ' ' . 
                ($employee->LastName ?? '')
            );

            // Create news feed entry (puid will be auto-generated)
            $newsFeed = NewsFeed::create([
                'corpId' => $request->corpId,
                'EmpCode' => $request->EmpCode,
                'companyName' => $request->companyName,
                'employeeFullName' => $employeeFullName,
                'body' => $request->body,
                'date' => $request->date,
                'time' => $request->time,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'News feed created successfully',
                'data' => $newsFeed
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating news feed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating news feed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new news feed review
     * POST /newsfeed-reviews
     * If the same EmpCode already reviewed the same puid, it will update the existing review
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeReview(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'puid' => 'required|string|max:100',
            'EmpCode' => 'required|string|max:20',
            'companyName' => 'required|string|max:100',
            'isLiked' => 'required|string|in:0,1',
            'comment' => 'nullable|string',
            'date' => 'required|string|max:20',
            'time' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if the news feed exists
            $newsFeed = NewsFeed::where('puid', $request->puid)->first();

            if (!$newsFeed) {
                return response()->json([
                    'status' => false,
                    'message' => 'News feed not found with the provided puid',
                    'puid' => $request->puid
                ], 404);
            }

            // Fetch employee details from employee_details table
            $employee = \DB::table('employee_details')
                ->where('corp_id', $request->corpId)
                ->where('EmpCode', $request->EmpCode)
                ->first();

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found with the provided EmpCode',
                    'corpId' => $request->corpId,
                    'EmpCode' => $request->EmpCode
                ], 404);
            }

            // Concatenate employee name
            $employeeFullName = trim(
                ($employee->FirstName ?? '') . ' ' . 
                ($employee->MiddleName ?? '') . ' ' . 
                ($employee->LastName ?? '')
            );

            // Check if this employee already reviewed this news feed
            $existingReview = NewsFeedReview::where('puid', $request->puid)
                ->where('corpId', $request->corpId)
                ->where('EmpCode', $request->EmpCode)
                ->first();

            if ($existingReview) {
                // Update existing review
                $existingReview->update([
                    'companyName' => $request->companyName,
                    'employeeFullName' => $employeeFullName,
                    'isLiked' => $request->isLiked,
                    'comment' => $request->comment,
                    'date' => $request->date,
                    'time' => $request->time,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Review updated successfully',
                    'action' => 'updated',
                    'data' => $existingReview->fresh()
                ], 200);
            }

            // Create new review
            $review = NewsFeedReview::create([
                'corpId' => $request->corpId,
                'puid' => $request->puid,
                'EmpCode' => $request->EmpCode,
                'companyName' => $request->companyName,
                'employeeFullName' => $employeeFullName,
                'isLiked' => $request->isLiked,
                'comment' => $request->comment,
                'date' => $request->date,
                'time' => $request->time,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Review created successfully',
                'action' => 'created',
                'data' => $review
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating news feed review: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all news feeds with their reviews and calculated duration
     * GET /newsfeed-with-reviews?corpId={corpId}&companyName={companyName}
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWithReviews(Request $request)
    {
        // Validate query parameters
        $validator = Validator::make($request->query(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $corpId = $request->query('corpId');
            $companyName = $request->query('companyName');

            // Build query with filters
            $query = NewsFeed::with('reviews')
                ->where('corpId', $corpId);

            // Add optional companyName filter
            if ($companyName) {
                $query->where('companyName', $companyName);
            }

            // Get filtered news feeds
            $newsFeeds = $query->orderBy('created_at', 'desc')->get();

            if ($newsFeeds->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No news feeds found',
                    'filters' => [
                        'corpId' => $corpId,
                        'companyName' => $companyName
                    ],
                    'data' => []
                ]);
            }

            // Format the response
            $formattedData = $newsFeeds->map(function ($newsFeed) {
                // Calculate duration for news feed
                $duration = $this->calculateDuration($newsFeed->date, $newsFeed->time);

                // Count likes
                $likesCount = $newsFeed->reviews()->where('isLiked', '1')->count();

                // Format reviews
                $reviews = $newsFeed->reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'corpId' => $review->corpId,
                        'puid' => $review->puid,
                        'EmpCode' => $review->EmpCode,
                        'companyName' => $review->companyName,
                        'employeeFullName' => $review->employeeFullName,
                        'isLiked' => $review->isLiked,
                        'comment' => $review->comment,
                        'date' => $review->date,
                        'time' => $review->time,
                        'duration' => $this->calculateDuration($review->date, $review->time),
                    ];
                });

                return [
                    'id' => $newsFeed->id,
                    'corpId' => $newsFeed->corpId,
                    'puid' => $newsFeed->puid,
                    'EmpCode' => $newsFeed->EmpCode,
                    'companyName' => $newsFeed->companyName,
                    'employeeFullName' => $newsFeed->employeeFullName,
                    'body' => $newsFeed->body,
                    'date' => $newsFeed->date,
                    'time' => $newsFeed->time,
                    'duration' => $duration,
                    'likesCount' => $likesCount,
                    'reviews' => $reviews,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'News feeds retrieved successfully',
                'filters' => [
                    'corpId' => $corpId,
                    'companyName' => $companyName
                ],
                'count' => $formattedData->count(),
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching news feeds with reviews: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching news feeds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate duration from date and time
     *
     * @param string $date
     * @param string $time
     * @return string
     */
    private function calculateDuration($date, $time)
    {
        try {
            // Combine date and time
            $dateTimeString = $date . ' ' . $time;
            
            // Try to parse the datetime
            $postDateTime = Carbon::parse($dateTimeString);
            $now = Carbon::now();

            // Calculate difference in hours
            $diffInHours = $postDateTime->diffInHours($now);
            $diffInDays = $postDateTime->diffInDays($now);

            if ($diffInHours < 1) {
                $diffInMinutes = $postDateTime->diffInMinutes($now);
                if ($diffInMinutes < 1) {
                    return 'Just now';
                }
                return $diffInMinutes . ' minute' . ($diffInMinutes > 1 ? 's' : '') . ' ago';
            } elseif ($diffInHours < 24) {
                return $diffInHours . ' hour' . ($diffInHours > 1 ? 's' : '') . ' ago';
            } elseif ($diffInDays == 1) {
                return 'Yesterday';
            } elseif ($diffInDays < 7) {
                return $diffInDays . ' day' . ($diffInDays > 1 ? 's' : '') . ' ago';
            } elseif ($diffInDays < 30) {
                $weeks = floor($diffInDays / 7);
                return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
            } elseif ($diffInDays < 365) {
                $months = floor($diffInDays / 30);
                return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
            } else {
                $years = floor($diffInDays / 365);
                return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
            }

        } catch (\Exception $e) {
            Log::error('Error calculating duration: ' . $e->getMessage());
            return 'Unknown';
        }
    }

    /**
     * Delete a news feed review by puid
     * DELETE /newsfeed-reviews/{puid}?corpId={corpId}&EmpCode={EmpCode}
     *
     * @param Request $request
     * @param string $puid
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteReview(Request $request, $puid)
    {
        // Validate query parameters
        $validator = Validator::make($request->query(), [
            'corpId' => 'required|string|max:10',
            'EmpCode' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $corpId = $request->query('corpId');
            $empCode = $request->query('EmpCode');

            // Find the review by puid, corpId, and EmpCode
            $review = NewsFeedReview::where('puid', $puid)
                ->where('corpId', $corpId)
                ->where('EmpCode', $empCode)
                ->first();

            if (!$review) {
                return response()->json([
                    'status' => false,
                    'message' => 'Review not found with the provided puid, corpId, and EmpCode',
                    'puid' => $puid,
                    'corpId' => $corpId,
                    'EmpCode' => $empCode
                ], 404);
            }

            // Delete the review
            $review->delete();

            return response()->json([
                'status' => true,
                'message' => 'Review deleted successfully',
                'puid' => $puid
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting news feed review: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting review',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
