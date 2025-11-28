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
            'empCode' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = FmsEmployeeDocument::where('corpId', $request->corpId)
            ->where('companyName', $request->companyName)
            ->where('fileCategory', $request->fileCategory);

        // Add optional empCode filter
        if ($request->filled('empCode')) {
            $query->where('empCode', $request->empCode);
        }

        $files = $query->orderBy('created_at', 'desc')->get();

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

    /**
     * Delete a document from fms_employee_documents
     */
    public function deleteDocument($id)
    {
        $document = FmsEmployeeDocument::find($id);
        
        if (!$document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found'
            ], 404);
        }

        // Store file info before deletion
        $corpId = $document->corpId;
        $fileSize = $document->file_size;
        $fileName = $document->filename;
        $filePath = $document->file;

        // Delete physical file from storage if path exists
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        // Delete database record
        $document->delete();

        return response()->json([
            'status' => true,
            'message' => 'Document deleted successfully',
            'deletedDocument' => [
                'id' => $id,
                'filename' => $fileName,
                'corpId' => $corpId,
                'fileSize' => round($fileSize / 1048576, 2) . ' MB'
            ]
        ]);
    }

    /**
     * Get storage statistics: total files, total GB used, available space
     */
    public function storageStatistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corpId' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $corpId = $request->corpId;

        // Get total files count
        $totalFiles = FmsEmployeeDocument::where('corpId', $corpId)->count();

        // Get total storage used (in bytes) from fms_employee_documents
        $totalUsedBytes = FmsEmployeeDocument::where('corpId', $corpId)
            ->sum('file_size');

        // Get storage quota from company_storage (aggregate all records for this corpId)
        $companyStorages = CompanyStorage::where('corpId', $corpId)->get();
        
        if ($companyStorages->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Company storage record not found for this corpId'
            ], 404);
        }

        // Calculate total quota in bytes
        $totalQuotaBytes = 0;
        foreach ($companyStorages as $storage) {
            $size = $storage->size;
            $unit = strtoupper($storage->sizeUit);
            
            if ($unit === 'GB') {
                $totalQuotaBytes += $size * 1073741824; // GB to bytes
            } elseif ($unit === 'MB') {
                $totalQuotaBytes += $size * 1048576; // MB to bytes
            } elseif ($unit === 'KB') {
                $totalQuotaBytes += $size * 1024; // KB to bytes
            } else {
                $totalQuotaBytes += $size; // Already in bytes
            }
        }

        $availableBytes = max(0, $totalQuotaBytes - $totalUsedBytes);

        // Convert to GB
        $totalUsedGB = round($totalUsedBytes / 1073741824, 4);
        $totalQuotaGB = round($totalQuotaBytes / 1073741824, 4);
        $availableGB = round($availableBytes / 1073741824, 4);

        // Convert to MB as well
        $totalUsedMB = round($totalUsedBytes / 1048576, 2);
        $totalQuotaMB = round($totalQuotaBytes / 1048576, 2);
        $availableMB = round($availableBytes / 1048576, 2);

        // Calculate usage percentage
        $usagePercentage = $totalQuotaBytes > 0 ? round(($totalUsedBytes / $totalQuotaBytes) * 100, 2) : 0;

        return response()->json([
            'status' => true,
            'corpId' => $corpId,
            'statistics' => [
                'totalFiles' => $totalFiles,
                'storage' => [
                    'usedGB' => $totalUsedGB,
                    'quotaGB' => $totalQuotaGB,
                    'availableGB' => $availableGB,
                    'usedMB' => $totalUsedMB,
                    'quotaMB' => $totalQuotaMB,
                    'availableMB' => $availableMB,
                    'usagePercentage' => $usagePercentage . '%'
                ],
                'bytes' => [
                    'used' => $totalUsedBytes,
                    'quota' => $totalQuotaBytes,
                    'available' => $availableBytes
                ]
            ]
        ]);
    }
}
