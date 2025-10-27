<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Esi;
use Illuminate\Support\Facades\Validator;

class EsiController extends Controller
{
    /**
     * Insert new ESI record
     */
    public function addEsi(Request $request)
    {
        // Add logging to debug
        \Log::info('ESI Add API called', $request->all());
        
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'corpId' => 'required|string',
                'companyName' => 'required|string',
                'state' => 'required|string',
                'incomeRange' => 'nullable|string',
                'esiAmount' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                \Log::error('ESI validation failed', $validator->errors()->toArray());
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Set default values for null or empty fields
            $data = $request->only(['corpId', 'companyName', 'state', 'incomeRange', 'esiAmount']);
            $data['incomeRange'] = $data['incomeRange'] ?? '0';
            $data['esiAmount'] = $data['esiAmount'] ?? '0';

            \Log::info('ESI data to be inserted', $data);

            // Create new ESI record
            $esi = Esi::create($data);

            \Log::info('ESI created successfully', ['id' => $esi->id]);

            return response()->json([
                'status' => true,
                'message' => 'ESI record created successfully',
                'data' => $esi
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error creating ESI record', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Error creating ESI record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch ESI records with optional filtering
     * Supports filtering by corpId, companyName, state
     */
    public function getEsi(Request $request)
    {
        \Log::info('ESI Get API called', $request->all());
        
        try {
            $query = Esi::query();
            $appliedFilters = [];

            // Filter by corpId (exact match)
            if ($request->has('corpId') && !empty($request->corpId)) {
                $query->where('corpId', $request->corpId);
                $appliedFilters['corpId'] = $request->corpId;
            }

            // Filter by companyName (supports both exact and partial match)
            if ($request->has('companyName') && !empty($request->companyName)) {
                $companyName = $request->companyName;
                
                // Check if exact match is requested
                if ($request->has('exactMatch') && $request->exactMatch === true) {
                    $query->where('companyName', $companyName);
                    $appliedFilters['companyName'] = $companyName . ' (exact match)';
                } else {
                    // Default to partial match
                    $query->where('companyName', 'like', '%' . $companyName . '%');
                    $appliedFilters['companyName'] = $companyName . ' (partial match)';
                }
            }

            // Filter by state (partial match)
            if ($request->has('state') && !empty($request->state)) {
                $query->where('state', 'like', '%' . $request->state . '%');
                $appliedFilters['state'] = $request->state;
            }

            // Filter by incomeRange (partial match)
            if ($request->has('incomeRange') && !empty($request->incomeRange)) {
                $query->where('incomeRange', 'like', '%' . $request->incomeRange . '%');
                $appliedFilters['incomeRange'] = $request->incomeRange;
            }

            // Filter by esiAmount (exact match)
            if ($request->has('esiAmount') && !empty($request->esiAmount)) {
                $query->where('esiAmount', $request->esiAmount);
                $appliedFilters['esiAmount'] = $request->esiAmount;
            }

            // Execute query with ordering
            $esiRecords = $query->orderBy('created_at', 'desc')->get();

            \Log::info('ESI records retrieved', [
                'count' => $esiRecords->count(),
                'applied_filters' => $appliedFilters
            ]);

            return response()->json([
                'status' => true,
                'message' => 'ESI records retrieved successfully',
                'data' => $esiRecords,
                'count' => $esiRecords->count(),
                'applied_filters' => $appliedFilters,
                'total_records' => Esi::count()
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error retrieving ESI records', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving ESI records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit/Update an ESI record
     */
    public function editEsi(Request $request, $id)
    {
        \Log::info('ESI Edit API called', ['id' => $id, 'data' => $request->all()]);
        
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'corpId' => 'sometimes|required|string',
                'companyName' => 'sometimes|required|string',
                'state' => 'sometimes|required|string',
                'incomeRange' => 'nullable|string',
                'esiAmount' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                \Log::error('ESI edit validation failed', $validator->errors()->toArray());
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Find the ESI record
            $esi = Esi::find($id);

            if (!$esi) {
                \Log::error('ESI record not found', ['id' => $id]);
                return response()->json([
                    'status' => false,
                    'message' => 'ESI record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = $request->all();
            
            // Set default values for null or empty fields if they are provided
            if ($request->has('incomeRange')) {
                $updateData['incomeRange'] = $request->incomeRange ?: '0';
            }
            if ($request->has('esiAmount')) {
                $updateData['esiAmount'] = $request->esiAmount ?: '0';
            }

            \Log::info('ESI data to be updated', $updateData);

            // Update the record
            $esi->update($updateData);

            \Log::info('ESI updated successfully', ['id' => $esi->id]);

            return response()->json([
                'status' => true,
                'message' => 'ESI record updated successfully',
                'data' => $esi->fresh()
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error updating ESI record', [
                'id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Error updating ESI record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an ESI record
     */
    public function deleteEsi($id)
    {
        \Log::info('ESI Delete API called', ['id' => $id]);
        
        try {
            // Find the ESI record
            $esi = Esi::find($id);

            if (!$esi) {
                \Log::error('ESI record not found for deletion', ['id' => $id]);
                return response()->json([
                    'status' => false,
                    'message' => 'ESI record not found'
                ], 404);
            }

            // Delete the record
            $esi->delete();

            \Log::info('ESI deleted successfully', ['id' => $id]);

            return response()->json([
                'status' => true,
                'message' => 'ESI record deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error deleting ESI record', [
                'id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Error deleting ESI record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single ESI record by ID
     */
    public function getEsiById($id)
    {
        \Log::info('ESI Get by ID API called', ['id' => $id]);
        
        try {
            $esi = Esi::find($id);

            if (!$esi) {
                \Log::error('ESI record not found', ['id' => $id]);
                return response()->json([
                    'status' => false,
                    'message' => 'ESI record not found'
                ], 404);
            }

            \Log::info('ESI record retrieved by ID', ['id' => $id]);

            return response()->json([
                'status' => true,
                'message' => 'ESI record retrieved successfully',
                'data' => $esi
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error retrieving ESI record by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving ESI record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
