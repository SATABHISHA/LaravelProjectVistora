<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NewsFeed;
use App\Models\NewsFeedReview;
use App\Models\NewsFeedLike;
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
            'isLiked' => 'nullable|string|in:0,1',
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
                $updateData = [
                    'companyName' => $request->companyName,
                    'employeeFullName' => $employeeFullName,
                    'date' => $request->date,
                    'time' => $request->time,
                ];

                // Only update isLiked if provided
                if ($request->has('isLiked')) {
                    $updateData['isLiked'] = $request->isLiked;
                }

                // Only update comment if provided
                if ($request->has('comment')) {
                    $updateData['comment'] = $request->comment;
                }

                $existingReview->update($updateData);

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
                'isLiked' => $request->isLiked ?? '0',
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
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
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
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            // Build query with filters
            $query = NewsFeed::with(['reviews', 'likes'])
                ->where('corpId', $corpId);

            // Add optional companyName filter
            if ($companyName) {
                $query->where('companyName', $companyName);
            }

            // Get paginated news feeds
            $newsFeeds = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

            if ($newsFeeds->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No news feeds found',
                    'filters' => [
                        'corpId' => $corpId,
                        'companyName' => $companyName
                    ],
                    'pagination' => [
                        'total' => 0,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'last_page' => 0,
                        'from' => null,
                        'to' => null
                    ],
                    'data' => []
                ]);
            }

            // Format the response
            $formattedData = $newsFeeds->map(function ($newsFeed) {
                // Calculate duration for news feed
                $duration = $this->calculateDuration($newsFeed->date, $newsFeed->time);

                // Count likes and comments from loaded collections
                $likesCount = $newsFeed->likes->count();
                $commentsCount = $newsFeed->reviews->where('comment', '!=', null)->where('comment', '!=', '')->count();

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
                        'date' => $this->formatDate($review->date),
                        'time' => $this->formatTime($review->time),
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
                    'date' => $this->formatDate($newsFeed->date),
                    'time' => $this->formatTime($newsFeed->time),
                    'duration' => $duration,
                    'likesCount' => $likesCount,
                    'commentsCount' => $commentsCount,
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
                'pagination' => [
                    'total' => $newsFeeds->total(),
                    'per_page' => $newsFeeds->perPage(),
                    'current_page' => $newsFeeds->currentPage(),
                    'last_page' => $newsFeeds->lastPage(),
                    'from' => $newsFeeds->firstItem(),
                    'to' => $newsFeeds->lastItem()
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

            // Calculate differences
            $diffInSeconds = $postDateTime->diffInSeconds($now);
            $diffInMinutes = $postDateTime->diffInMinutes($now);
            $diffInHours = $postDateTime->diffInHours($now);
            $diffInDays = $postDateTime->diffInDays($now);

            if ($diffInSeconds < 60) {
                if ($diffInSeconds < 5) {
                    return 'Just now';
                }
                return $diffInSeconds . ' second' . ($diffInSeconds > 1 ? 's' : '') . ' ago';
            } elseif ($diffInMinutes < 60) {
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
     * Format date to "15 November 2025" format
     *
     * @param string $date
     * @return string
     */
    private function formatDate($date)
    {
        try {
            return Carbon::parse($date)->format('d F Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format time to "02:30 PM" format
     *
     * @param string $time
     * @return string
     */
    private function formatTime($time)
    {
        try {
            return Carbon::parse($time)->format('h:i A');
        } catch (\Exception $e) {
            return $time;
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

    /**
     * Update a news feed entry
     * PUT/PATCH /newsfeed/{puid}
     *
     * @param Request $request
     * @param string $puid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $puid)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'EmpCode' => 'required|string|max:20',
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
            // Find the news feed by puid
            $newsFeed = NewsFeed::where('puid', $puid)
                ->where('corpId', $request->corpId)
                ->where('EmpCode', $request->EmpCode)
                ->first();

            if (!$newsFeed) {
                return response()->json([
                    'status' => false,
                    'message' => 'News feed not found or you do not have permission to update it',
                    'puid' => $puid
                ], 404);
            }

            // Update the news feed
            $newsFeed->update([
                'body' => $request->body,
                'date' => $request->date,
                'time' => $request->time,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'News feed updated successfully',
                'data' => $newsFeed
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating news feed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating news feed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a news feed entry
     * DELETE /newsfeed/{puid}
     *
     * @param Request $request
     * @param string $puid
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $puid)
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

            // Find the news feed by puid, corpId, and EmpCode
            $newsFeed = NewsFeed::where('puid', $puid)
                ->where('corpId', $corpId)
                ->where('EmpCode', $empCode)
                ->first();

            if (!$newsFeed) {
                return response()->json([
                    'status' => false,
                    'message' => 'News feed not found or you do not have permission to delete it',
                    'puid' => $puid
                ], 404);
            }

            // Delete the news feed (cascade will delete all reviews and likes)
            $newsFeed->delete();

            return response()->json([
                'status' => true,
                'message' => 'News feed deleted successfully',
                'puid' => $puid
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting news feed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting news feed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single news feed entry with reviews and likes
     * GET /newsfeed/{puid}
     *
     * @param Request $request
     * @param string $puid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $puid)
    {
        // Validate query parameters
        $validator = Validator::make($request->query(), [
            'corpId' => 'required|string|max:10',
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

            // Find the news feed with reviews and likes
            $newsFeed = NewsFeed::with(['reviews', 'likes'])
                ->where('puid', $puid)
                ->where('corpId', $corpId)
                ->first();

            if (!$newsFeed) {
                return response()->json([
                    'status' => false,
                    'message' => 'News feed not found',
                    'puid' => $puid
                ], 404);
            }

            // Calculate duration
            $duration = $this->calculateDuration($newsFeed->date, $newsFeed->time);

            // Count likes and comments
            $likesCount = $newsFeed->likes->count();
            $commentsCount = $newsFeed->reviews->where('comment', '!=', null)->where('comment', '!=', '')->count();

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
                    'date' => $this->formatDate($review->date),
                    'time' => $this->formatTime($review->time),
                    'duration' => $this->calculateDuration($review->date, $review->time),
                ];
            });

            // Format likes
            $likes = $newsFeed->likes->map(function ($like) {
                return [
                    'id' => $like->id,
                    'corpId' => $like->corpId,
                    'EmpCode' => $like->EmpCode,
                    'companyName' => $like->companyName,
                    'employeeFullName' => $like->employeeFullName,
                    'date' => $this->formatDate($like->date),
                    'time' => $this->formatTime($like->time),
                    'duration' => $this->calculateDuration($like->date, $like->time),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'News feed retrieved successfully',
                'data' => [
                    'id' => $newsFeed->id,
                    'corpId' => $newsFeed->corpId,
                    'puid' => $newsFeed->puid,
                    'EmpCode' => $newsFeed->EmpCode,
                    'companyName' => $newsFeed->companyName,
                    'employeeFullName' => $newsFeed->employeeFullName,
                    'body' => $newsFeed->body,
                    'date' => $this->formatDate($newsFeed->date),
                    'time' => $this->formatTime($newsFeed->time),
                    'duration' => $duration,
                    'likesCount' => $likesCount,
                    'commentsCount' => $commentsCount,
                    'reviews' => $reviews,
                    'likes' => $likes,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching news feed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching news feed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Like a news feed post
     * POST /newsfeed/{puid}/like
     *
     * @param Request $request
     * @param string $puid
     * @return \Illuminate\Http\JsonResponse
     */
    public function like(Request $request, $puid)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'EmpCode' => 'required|string|max:20',
            'companyName' => 'required|string|max:100',
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
            $newsFeed = NewsFeed::where('puid', $puid)->first();

            if (!$newsFeed) {
                return response()->json([
                    'status' => false,
                    'message' => 'News feed not found with the provided puid',
                    'puid' => $puid
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

            // Check if already liked
            $existingLike = NewsFeedLike::where('puid', $puid)
                ->where('corpId', $request->corpId)
                ->where('EmpCode', $request->EmpCode)
                ->first();

            if ($existingLike) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have already liked this post',
                    'data' => $existingLike
                ], 409);
            }

            // Create new like
            $like = NewsFeedLike::create([
                'corpId' => $request->corpId,
                'puid' => $puid,
                'EmpCode' => $request->EmpCode,
                'companyName' => $request->companyName,
                'employeeFullName' => $employeeFullName,
                'date' => $request->date,
                'time' => $request->time,
            ]);

            // Get updated likes count
            $likesCount = NewsFeedLike::where('puid', $puid)->count();

            return response()->json([
                'status' => true,
                'message' => 'Post liked successfully',
                'data' => $like,
                'likesCount' => $likesCount
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error liking post: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while liking the post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unlike a news feed post
     * DELETE /newsfeed/{puid}/unlike
     *
     * @param Request $request
     * @param string $puid
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlike(Request $request, $puid)
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

            // Find the like
            $like = NewsFeedLike::where('puid', $puid)
                ->where('corpId', $corpId)
                ->where('EmpCode', $empCode)
                ->first();

            if (!$like) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have not liked this post',
                    'puid' => $puid
                ], 404);
            }

            // Delete the like
            $like->delete();

            // Get updated likes count
            $likesCount = NewsFeedLike::where('puid', $puid)->count();

            return response()->json([
                'status' => true,
                'message' => 'Post unliked successfully',
                'puid' => $puid,
                'likesCount' => $likesCount
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error unliking post: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while unliking the post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all likes for a news feed post
     * GET /newsfeed/{puid}/likes
     *
     * @param Request $request
     * @param string $puid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLikes(Request $request, $puid)
    {
        // Validate query parameters
        $validator = Validator::make($request->query(), [
            'corpId' => 'required|string|max:10',
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

            // Check if the news feed exists
            $newsFeed = NewsFeed::where('puid', $puid)
                ->where('corpId', $corpId)
                ->first();

            if (!$newsFeed) {
                return response()->json([
                    'status' => false,
                    'message' => 'News feed not found',
                    'puid' => $puid
                ], 404);
            }

            // Get all likes for this post
            $likes = NewsFeedLike::where('puid', $puid)
                ->orderBy('created_at', 'desc')
                ->get();

            // Format likes
            $formattedLikes = $likes->map(function ($like) {
                return [
                    'id' => $like->id,
                    'corpId' => $like->corpId,
                    'EmpCode' => $like->EmpCode,
                    'companyName' => $like->companyName,
                    'employeeFullName' => $like->employeeFullName,
                    'date' => $this->formatDate($like->date),
                    'time' => $this->formatTime($like->time),
                    'duration' => $this->calculateDuration($like->date, $like->time),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Likes retrieved successfully',
                'puid' => $puid,
                'count' => $formattedLikes->count(),
                'data' => $formattedLikes
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching likes: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching likes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get likes count for a news feed post
     * GET /newsfeed/{puid}/likes-count
     *
     * @param Request $request
     * @param string $puid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLikesCount(Request $request, $puid)
    {
        // Validate query parameters
        $validator = Validator::make($request->query(), [
            'corpId' => 'required|string|max:10',
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

            // Check if the news feed exists
            $newsFeed = NewsFeed::where('puid', $puid)
                ->where('corpId', $corpId)
                ->first();

            if (!$newsFeed) {
                return response()->json([
                    'status' => false,
                    'message' => 'News feed not found',
                    'puid' => $puid
                ], 404);
            }

            // Get likes count
            $likesCount = NewsFeedLike::where('puid', $puid)->count();

            return response()->json([
                'status' => true,
                'message' => 'Likes count retrieved successfully',
                'puid' => $puid,
                'likesCount' => $likesCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching likes count: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching likes count',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
