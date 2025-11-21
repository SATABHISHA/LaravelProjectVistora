<?php

namespace App\Http\Controllers;

use App\Models\FmsEmployeeDocument;
use App\Models\CompanyStorage;
use App\Models\CompanyDetails;
use App\Models\EmploymentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FmsController extends Controller
{
    /**
     * Upload document with file size validation and storage quota check
     */
    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'empCode' => 'required|string|max:20',
            'fileCategory' => 'required|string|max:50',
            'file' => 'required|file|max:5120', // 5MB = 5120 KB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $fileSize = $file->getSize(); // Size in bytes

        // Check 5MB limit
        if ($fileSize > 5242880) { // 5 * 1024 * 1024
            return response()->json([
                'status' => false,
                'message' => 'File exceeds 5MB limit.'
            ], 400);
        }

        // Check company storage quota
        $corpId = $request->corpId;
        $storageRecords = CompanyStorage::where('corpId', $corpId)->get();
        
        if ($storageRecords->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Storage limit exceeded. Please contact us to upgrade your plan.'
            ], 400);
        }

        // Calculate total allocated storage in bytes
        $totalAllocatedBytes = 0;
        foreach ($storageRecords as $record) {
            $multiplier = match(strtoupper($record->sizeUit)) {
                'KB' => 1024,
                'MB' => 1048576,
                'GB' => 1073741824,
                default => 0
            };
            $totalAllocatedBytes += $record->size * $multiplier;
        }

        // Calculate currently used storage
        $usedBytes = FmsEmployeeDocument::where('corpId', $corpId)->sum('file_size');
        
        // Check if adding this file would exceed quota
        if (($usedBytes + $fileSize) > $totalAllocatedBytes) {
            return response()->json([
                'status' => false,
                'message' => 'Storage limit exceeded. Please contact us to upgrade your plan.'
            ], 400);
        }

        // Store file
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = time() . '_' . $originalName;
        $filePath = $file->storeAs('fms_documents', $filename, 'public');

        // Save to database
        $document = FmsEmployeeDocument::create([
            'corpId' => $request->corpId,
            'companyName' => $request->companyName,
            'empCode' => $request->empCode,
            'fileCategory' => $request->fileCategory,
            'filename' => $originalName,
            'file' => $filePath,
            'file_size' => $fileSize,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'id' => $document->id,
                'filename' => $document->filename,
                'file_size' => $document->file_size,
                'file_size_mb' => round($fileSize / 1048576, 2),
                'uploaded_at' => $document->created_at,
                'storage_used' => round(($usedBytes + $fileSize) / 1048576, 2) . ' MB',
                'storage_allocated' => round($totalAllocatedBytes / 1048576, 2) . ' MB',
            ]
        ], 201);
    }

    /**
     * Get summary by company grouped by file category
     */
    public function summaryByCompany(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $summary = FmsEmployeeDocument::where('corpId', $request->corpId)
            ->where('companyName', $request->companyName)
            ->select('fileCategory')
            ->selectRaw('COUNT(*) as totalFiles')
            ->selectRaw('SUM(file_size) as totalSizeBytes')
            ->groupBy('fileCategory')
            ->get();

        $formattedSummary = $summary->map(function ($item) {
            $sizeInBytes = $item->totalSizeBytes;
            $sizeInMB = $sizeInBytes / 1048576;
            $sizeInGB = $sizeInBytes / 1073741824;

            return [
                'fileCategory' => $item->fileCategory,
                'totalFiles' => $item->totalFiles,
                'totalSize' => $sizeInGB >= 1 
                    ? round($sizeInGB, 2) . ' GB' 
                    : round($sizeInMB, 2) . ' MB',
                'totalSizeBytes' => $sizeInBytes,
            ];
        });

        return response()->json([
            'status' => true,
            'corpId' => $request->corpId,
            'companyName' => $request->companyName,
            'summary' => $formattedSummary,
        ]);
    }

    /**
     * Get files by category with download URLs
     */
    public function filesByCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'fileCategory' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $files = FmsEmployeeDocument::where('corpId', $request->corpId)
            ->where('companyName', $request->companyName)
            ->where('fileCategory', $request->fileCategory)
            ->orderBy('created_at', 'desc')
            ->get();

        $fileList = $files->map(function ($file) {
            $extension = pathinfo($file->filename, PATHINFO_EXTENSION);
            
            return [
                'id' => $file->id,
                'filename' => $file->filename,
                'fileType' => strtoupper($extension),
                'empCode' => $file->empCode,
                'file_size' => round($file->file_size / 1048576, 2) . ' MB',
                'downloadUrl' => url('storage/' . $file->file),
                'uploaded_at' => $file->created_at,
            ];
        });

        return response()->json([
            'status' => true,
            'corpId' => $request->corpId,
            'companyName' => $request->companyName,
            'fileCategory' => $request->fileCategory,
            'totalFiles' => $fileList->count(),
            'files' => $fileList,
        ]);
    }

    /**
     * Company storage overview with multi-table joins
     */
    public function companyStorageOverview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $corpId = $request->corp_id;

        // Get unique companies from company_details
        $companies = CompanyDetails::where('corp_id', $corpId)
            ->select('company_name')
            ->distinct()
            ->get();

        $overview = [];

        foreach ($companies as $company) {
            $companyName = $company->company_name;

            // Count employees from employment_details
            $totalEmployees = EmploymentDetail::where('corp_id', $corpId)
                ->where('company_name', $companyName)
                ->distinct('EmpCode')
                ->count('EmpCode');

            // Calculate storage used from fms_employee_documents
            $totalStorageBytes = FmsEmployeeDocument::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->sum('file_size');

            $totalStorageGB = round($totalStorageBytes / 1073741824, 4);

            $overview[] = [
                'companyName' => $companyName,
                'totalEmployees' => $totalEmployees,
                'totalStorageUsedGB' => $totalStorageGB,
                'totalStorageUsedBytes' => $totalStorageBytes,
            ];
        }

        return response()->json([
            'status' => true,
            'corp_id' => $corpId,
            'totalCompanies' => count($overview),
            'companies' => $overview,
        ]);
    }
}
