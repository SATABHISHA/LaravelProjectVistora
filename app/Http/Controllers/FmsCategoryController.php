<?php

namespace App\Http\Controllers;

use App\Models\FmsCategory;
use App\Models\FmsEmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FmsCategoryController extends Controller
{
    // Create category record
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'empCode' => 'required|string|max:20',
            'fullName' => 'required|string|max:200',
            'fileCategory' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure uniqueness per employee/category
        $existing = FmsCategory::where('corpId', $request->corpId)
            ->where('companyName', $request->companyName)
            ->where('empCode', $request->empCode)
            ->where('fileCategory', $request->fileCategory)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'Category already exists for this employee'
            ], 409);
        }

        $category = FmsCategory::create($request->only([
            'corpId','companyName','empCode','fullName','fileCategory'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ]);
    }

    // Update category record
    public function update(Request $request, $id)
    {
        $category = FmsCategory::find($id);
        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fullName' => 'sometimes|required|string|max:200',
            'fileCategory' => 'sometimes|required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent duplicate if fileCategory changed
        if ($request->filled('fileCategory') && $request->fileCategory !== $category->fileCategory) {
            $duplicate = FmsCategory::where('corpId', $category->corpId)
                ->where('companyName', $category->companyName)
                ->where('empCode', $category->empCode)
                ->where('fileCategory', $request->fileCategory)
                ->first();
            if ($duplicate) {
                return response()->json([
                    'status' => false,
                    'message' => 'Updated fileCategory would duplicate existing record'
                ], 409);
            }
        }

        $category->fill($request->only(['fullName','fileCategory']));
        $category->save();

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    // Delete category record
    public function destroy($id)
    {
        $category = FmsCategory::find($id);
        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found'
            ], 404);
        }
        $category->delete();
        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    // Summary endpoint
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'empCode' => 'sometimes|string|max:20',
            'fileCategory' => 'sometimes|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $corpId = $request->corpId;
        $companyName = $request->companyName;

        // Build category filter query
        $categoryQuery = FmsCategory::where('corpId', $corpId)
            ->where('companyName', $companyName);

        if ($request->filled('empCode')) {
            $categoryQuery->where('empCode', $request->empCode);
        }
        if ($request->filled('fileCategory')) {
            $categoryQuery->where('fileCategory', $request->fileCategory);
        }

        // Get distinct categories with id
        $categories = $categoryQuery->select('id', 'fileCategory')
            ->get();

        // If no categories found, return empty
        if ($categories->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No categories found',
                'filters' => [
                    'corpId' => $corpId,
                    'companyName' => $companyName,
                    'empCode' => $request->empCode ?? null,
                    'fileCategory' => $request->fileCategory ?? null,
                ],
                'totalCategories' => 0,
                'data' => []
            ]);
        }

        // Build aggregation for each category
        $data = $categories->map(function ($category) use ($corpId, $companyName, $request) {
            $docQuery = FmsEmployeeDocument::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('fileCategory', $category->fileCategory);

            if ($request->filled('empCode')) {
                $docQuery->where('empCode', $request->empCode);
            }

            $stats = $docQuery->selectRaw('COUNT(*) as totalFiles, COALESCE(SUM(file_size), 0) as totalFileSizeBytes')
                ->first();

            $bytes = (int)($stats->totalFileSizeBytes ?? 0);
            $mb = $bytes > 0 ? round($bytes / 1048576, 4) : 0;

            return [
                'id' => $category->id,
                'fileCategory' => $category->fileCategory,
                'totalFiles' => (int)($stats->totalFiles ?? 0),
                'totalFileSizeBytes' => $bytes,
                'totalFileSizeMB' => $mb,
            ];
        })->sortBy('fileCategory')->values();

        return response()->json([
            'status' => true,
            'message' => 'Category summary retrieved successfully',
            'filters' => [
                'corpId' => $corpId,
                'companyName' => $companyName,
                'empCode' => $request->empCode ?? null,
                'fileCategory' => $request->fileCategory ?? null,
            ],
            'totalCategories' => $data->count(),
            'data' => $data
        ]);
    }
}
