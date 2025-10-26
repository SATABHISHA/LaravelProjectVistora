<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProfessionalTax;
use Illuminate\Support\Facades\Validator;

class ProfessionalTaxController extends Controller
{
    /**
     * Add a new professional tax record
     */
    public function addProfessionalTax(Request $request)
    {
        // Add logging to debug
        \Log::info('Professional Tax Add API called', $request->all());
        
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'corpId' => 'required|string',
                'companyName' => 'required|string',
                'state' => 'required|string',
                'minIncome' => 'nullable|string',
                'maxIncome' => 'nullable|string',
                'aboveIncome' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed', $validator->errors()->toArray());
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Set default values for null or empty income fields
            $data = $request->only(['corpId', 'companyName', 'state', 'minIncome', 'maxIncome', 'aboveIncome']);
            $data['minIncome'] = $data['minIncome'] ?? '0';
            $data['maxIncome'] = $data['maxIncome'] ?? '0';
            $data['aboveIncome'] = $data['aboveIncome'] ?? '0';

            \Log::info('Data to be inserted', $data);

            // Create new professional tax record
            $professionalTax = ProfessionalTax::create($data);

            \Log::info('Professional tax created successfully', ['id' => $professionalTax->id]);

            return response()->json([
                'status' => true,
                'message' => 'Professional tax record created successfully',
                'data' => $professionalTax
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error creating professional tax record', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Error creating professional tax record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all professional tax records with optional filtering
     * Supports filtering by corpId, companyName, state with various matching options
     */
    public function getProfessionalTax(Request $request)
    {
        \Log::info('Professional Tax Get API called', $request->all());
        
        try {
            $query = ProfessionalTax::query();
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

            // Filter by income ranges (optional)
            if ($request->has('minIncomeFrom') && !empty($request->minIncomeFrom)) {
                $query->where('minIncome', '>=', $request->minIncomeFrom);
                $appliedFilters['minIncomeFrom'] = $request->minIncomeFrom;
            }

            if ($request->has('maxIncomeUpTo') && !empty($request->maxIncomeUpTo)) {
                $query->where('maxIncome', '<=', $request->maxIncomeUpTo);
                $appliedFilters['maxIncomeUpTo'] = $request->maxIncomeUpTo;
            }

            // Combined corpId AND companyName filter (when both provided)
            if ($request->has('corpId') && $request->has('companyName') && 
                !empty($request->corpId) && !empty($request->companyName)) {
                \Log::info('Applying combined corpId and companyName filter', [
                    'corpId' => $request->corpId,
                    'companyName' => $request->companyName
                ]);
            }

            // Execute query with ordering
            $professionalTaxRecords = $query->orderBy('created_at', 'desc')->get();

            \Log::info('Professional Tax records retrieved', [
                'count' => $professionalTaxRecords->count(),
                'applied_filters' => $appliedFilters
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Professional tax records retrieved successfully',
                'data' => $professionalTaxRecords,
                'count' => $professionalTaxRecords->count(),
                'applied_filters' => $appliedFilters,
                'total_records' => ProfessionalTax::count()
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error retrieving professional tax records', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving professional tax records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit/Update a professional tax record
     */
    public function editProfessionalTax(Request $request, $id)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'corpId' => 'sometimes|required|string',
                'companyName' => 'sometimes|required|string',
                'state' => 'sometimes|required|string',
                'minIncome' => 'nullable|string',
                'maxIncome' => 'nullable|string',
                'aboveIncome' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Find the professional tax record
            $professionalTax = ProfessionalTax::find($id);

            if (!$professionalTax) {
                return response()->json([
                    'status' => false,
                    'message' => 'Professional tax record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = $request->all();
            
            // Set default values for null or empty income fields
            if ($request->has('minIncome')) {
                $updateData['minIncome'] = $request->minIncome ?: '0';
            }
            if ($request->has('maxIncome')) {
                $updateData['maxIncome'] = $request->maxIncome ?: '0';
            }
            if ($request->has('aboveIncome')) {
                $updateData['aboveIncome'] = $request->aboveIncome ?: '0';
            }

            // Update the record
            $professionalTax->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'Professional tax record updated successfully',
                'data' => $professionalTax->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating professional tax record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a professional tax record
     */
    public function deleteProfessionalTax($id)
    {
        try {
            // Find the professional tax record
            $professionalTax = ProfessionalTax::find($id);

            if (!$professionalTax) {
                return response()->json([
                    'status' => false,
                    'message' => 'Professional tax record not found'
                ], 404);
            }

            // Delete the record
            $professionalTax->delete();

            return response()->json([
                'status' => true,
                'message' => 'Professional tax record deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting professional tax record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get professional tax records by corpId and companyName combination
     */
    public function getProfessionalTaxByCorpAndCompany(Request $request)
    {
        try {
            // Validate required parameters
            $validator = Validator::make($request->all(), [
                'corpId' => 'required|string',
                'companyName' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $query = ProfessionalTax::where('corpId', $request->corpId);
            
            // Support both exact and partial company name matching
            if ($request->has('exactMatch') && $request->exactMatch === true) {
                $query->where('companyName', $request->companyName);
            } else {
                $query->where('companyName', 'like', '%' . $request->companyName . '%');
            }

            $professionalTaxRecords = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => true,
                'message' => 'Professional tax records retrieved successfully',
                'data' => $professionalTaxRecords,
                'count' => $professionalTaxRecords->count(),
                'filters' => [
                    'corpId' => $request->corpId,
                    'companyName' => $request->companyName,
                    'exactMatch' => $request->exactMatch ?? false
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving professional tax records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single professional tax record by ID
     */
    public function getProfessionalTaxById($id)
    {
        try {
            $professionalTax = ProfessionalTax::find($id);

            if (!$professionalTax) {
                return response()->json([
                    'status' => false,
                    'message' => 'Professional tax record not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Professional tax record retrieved successfully',
                'data' => $professionalTax
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving professional tax record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
