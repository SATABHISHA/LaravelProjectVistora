<?php

namespace App\Http\Controllers;

use App\Models\CompanyStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CompanyStorageController extends Controller
{
    /**
     * List company storage entries (optionally filter by corpId)
     * GET /company-storage?corpId=XXX
     */
    public function index(Request $request)
    {
        try {
            $corpId = $request->query('corpId');
            $query = CompanyStorage::query();
            if ($corpId) {
                $query->where('corpId', $corpId);
            }
            $data = $query->orderBy('id', 'desc')->get();
            return response()->json([
                'status' => true,
                'message' => 'Company storage list retrieved successfully',
                'count' => $data->count(),
                'filters' => [ 'corpId' => $corpId ],
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error listing company storage: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a company storage entry for a corpId
     * POST /company-storage/{corpId}
     */
    public function store(Request $request, string $corpId)
    {
        $validator = Validator::make(array_merge($request->all(), ['corpId' => $corpId]), [
            'corpId' => 'required|string|max:10',
            'size' => 'required|integer|min:0',
            'sizeUit' => 'required|string|in:KB,MB,GB'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = CompanyStorage::create([
                'corpId' => $corpId,
                'size' => $request->size,
                'sizeUit' => $request->sizeUit,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Company storage created successfully',
                'data' => $record
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating company storage: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a company storage entry by corpId and id
     * PUT /company-storage/{corpId}/{id}
     */
    public function update(Request $request, string $corpId, int $id)
    {
        $validator = Validator::make(array_merge($request->all(), ['corpId' => $corpId, 'id' => $id]), [
            'corpId' => 'required|string|max:10',
            'id' => 'required|integer',
            'size' => 'nullable|integer|min:0',
            'sizeUit' => 'nullable|string|in:KB,MB,GB'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = CompanyStorage::where('corpId', $corpId)->where('id', $id)->first();
            if (!$record) {
                return response()->json([
                    'status' => false,
                    'message' => 'Record not found for given corpId and id',
                    'corpId' => $corpId,
                    'id' => $id
                ], 404);
            }

            $updateData = [];
            if ($request->has('size')) {
                $updateData['size'] = $request->size;
            }
            if ($request->has('sizeUit')) {
                $updateData['sizeUit'] = $request->sizeUit;
            }

            if (empty($updateData)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No updatable fields provided'
                ], 422);
            }

            $record->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'Company storage updated successfully',
                'data' => $record->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating company storage: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a company storage entry by corpId and id
     * DELETE /company-storage/{corpId}/{id}
     */
    public function destroy(string $corpId, int $id)
    {
        try {
            $record = CompanyStorage::where('corpId', $corpId)->where('id', $id)->first();
            if (!$record) {
                return response()->json([
                    'status' => false,
                    'message' => 'Record not found for given corpId and id',
                    'corpId' => $corpId,
                    'id' => $id
                ], 404);
            }
            $record->delete();
            return response()->json([
                'status' => true,
                'message' => 'Company storage deleted successfully',
                'corpId' => $corpId,
                'id' => $id
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting company storage: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Summary of storage for a corpId
     * GET /company-storage/summary/{corpId}
     */
    public function summary(string $corpId)
    {
        try {
            $human = request()->query('human') === 'true';
            $records = CompanyStorage::where('corpId', $corpId)->orderBy('id')->get();
            if ($records->isEmpty()) {
                $base = [
                    'status' => true,
                    'message' => 'No storage records found for corpId',
                    'corpId' => $corpId,
                    'totalRecords' => 0,
                    'totals' => [
                        'bytes' => 0,
                        'kilobytes' => 0,
                        'megabytes' => 0,
                        'gigabytes' => 0,
                    ],
                    'unitBreakdown' => [ 'KB' => 0, 'MB' => 0, 'GB' => 0 ],
                    'records' => []
                ];
                if ($human) {
                    $base['totalsHuman'] = [
                        'bestFit' => '0 B',
                        'bytes' => '0 B',
                        'kilobytes' => '0 KB',
                        'megabytes' => '0 MB',
                        'gigabytes' => '0 GB'
                    ];
                }
                return response()->json($base);
            }

            $unitMultipliers = [
                'KB' => 1024,
                'MB' => 1024 * 1024,
                'GB' => 1024 * 1024 * 1024,
            ];
            $allowedUnits = array_keys($unitMultipliers);
            $invalidUnits = [];
            $totalBytes = 0;
            $breakdown = [ 'KB' => 0, 'MB' => 0, 'GB' => 0 ];

            $recordList = $records->map(function ($r) use (&$totalBytes, $unitMultipliers, &$breakdown, $allowedUnits, &$invalidUnits, $human) {
                $unitRaw = strtoupper($r->sizeUit);
                if (!in_array($unitRaw, $allowedUnits)) {
                    $invalidUnits[$unitRaw] = ($invalidUnits[$unitRaw] ?? 0) + 1;
                }
                $multiplier = $unitMultipliers[$unitRaw] ?? 1; // treat unknown as bytes
                $bytes = (int)$r->size * $multiplier;
                $totalBytes += $bytes;
                if (isset($breakdown[$unitRaw])) {
                    $breakdown[$unitRaw] += (int)$r->size;
                }
                $rec = [
                    'id' => $r->id,
                    'size' => (int)$r->size,
                    'sizeUit' => $r->sizeUit,
                    'bytes' => $bytes,
                ];
                if ($human) {
                    $rec['human'] = $this->humanReadable($bytes);
                }
                return $rec;
            });

            $totals = [
                'bytes' => $totalBytes,
                'kilobytes' => round($totalBytes / 1024, 2),
                'megabytes' => round($totalBytes / (1024 * 1024), 2),
                'gigabytes' => round($totalBytes / (1024 * 1024 * 1024), 4),
            ];

            $response = [
                'status' => true,
                'message' => 'Company storage summary retrieved successfully',
                'corpId' => $corpId,
                'totalRecords' => $records->count(),
                'totals' => $totals,
                'unitBreakdown' => $breakdown,
                'records' => $recordList,
            ];
            if (!empty($invalidUnits)) {
                $response['invalidUnits'] = $invalidUnits;
            }
            if ($human) {
                $response['totalsHuman'] = [
                    'bestFit' => $this->humanReadable($totalBytes),
                    'bytes' => number_format($totalBytes) . ' B',
                    'kilobytes' => number_format($totals['kilobytes'], 2) . ' KB',
                    'megabytes' => number_format($totals['megabytes'], 2) . ' MB',
                    'gigabytes' => number_format($totals['gigabytes'], 4) . ' GB',
                ];
            }
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error summarizing company storage: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while summarizing storage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all storage records for a corpId
     * DELETE /company-storage/all/{corpId}
     */
    public function destroyAll(string $corpId)
    {
        try {
            $count = CompanyStorage::where('corpId', $corpId)->count();
            if ($count === 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'No records found to delete for corpId',
                    'corpId' => $corpId,
                    'deleted' => 0
                ]);
            }
            CompanyStorage::where('corpId', $corpId)->delete();
            return response()->json([
                'status' => true,
                'message' => 'All company storage records deleted',
                'corpId' => $corpId,
                'deleted' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting all company storage: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert bytes to a human-readable string choosing best unit.
     */
    private function humanReadable(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $kb = $bytes / 1024;
        if ($kb < 1024) {
            return round($kb, 2) . ' KB';
        }
        $mb = $kb / 1024;
        if ($mb < 1024) {
            return round($mb, 2) . ' MB';
        }
        $gb = $mb / 1024;
        return round($gb, 4) . ' GB';
    }
}
