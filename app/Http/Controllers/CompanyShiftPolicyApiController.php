<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompanyShiftPolicy;
use App\Models\ShiftPolicy;
use Illuminate\Database\QueryException;

class CompanyShiftPolicyApiController extends Controller
{
    /**
     * Add new company shift policy
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validate required fields
            $request->validate([
                'corp_id' => 'required|string',
                'company_name' => 'required|string',
                'shift_code' => 'required|string'
            ]);

            $data = $request->only(['corp_id', 'company_name', 'shift_code']);

            // Check if combination already exists
            $exists = CompanyShiftPolicy::where('corp_id', $data['corp_id'])
                ->where('company_name', $data['company_name'])
                ->where('shift_code', $data['shift_code'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'This combination of corp_id, company_name, and shift_code already exists.'
                ], 409);
            }

            $companyShiftPolicy = CompanyShiftPolicy::create($data);

            return response()->json([
                'status' => true,
                'message' => 'Company shift policy added successfully.',
                'data' => $companyShiftPolicy
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Database error: Duplicate entry or constraint violation.'
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error adding company shift policy: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete company shift policy by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        try {
            $companyShiftPolicy = CompanyShiftPolicy::find($id);

            if (!$companyShiftPolicy) {
                return response()->json([
                    'status' => false,
                    'message' => 'Company shift policy not found.'
                ], 404);
            }

            $companyShiftPolicy->delete();

            return response()->json([
                'status' => true,
                'message' => 'Company shift policy deleted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting company shift policy: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch all company shift policies with shift details
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchAll(Request $request)
    {
        try {
            // Optional filters
            $corpId = $request->query('corp_id');
            $companyName = $request->query('company_name');
            $shiftCode = $request->query('shift_code');

            $query = CompanyShiftPolicy::query();

            // Apply filters if provided
            if ($corpId) {
                $query->where('corp_id', $corpId);
            }

            if ($companyName) {
                $query->where('company_name', $companyName);
            }

            if ($shiftCode) {
                $query->where('shift_code', $shiftCode);
            }

            $companyShiftPolicies = $query->get();

            if ($companyShiftPolicies->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No company shift policies found.',
                    'data' => []
                ]);
            }

            // Fetch shift details for each company shift policy
            $result = $companyShiftPolicies->map(function ($companyShiftPolicy) {
                // Find shift policy details by matching corp_id and shift_code
                $shiftPolicy = ShiftPolicy::where('corp_id', $companyShiftPolicy->corp_id)
                    ->where('shift_code', $companyShiftPolicy->shift_code)
                    ->first();

                return [
                    'id' => $companyShiftPolicy->id,
                    'corp_id' => $companyShiftPolicy->corp_id,
                    'company_name' => $companyShiftPolicy->company_name,
                    'shift_code' => $companyShiftPolicy->shift_code,
                    'created_at' => $companyShiftPolicy->created_at,
                    'updated_at' => $companyShiftPolicy->updated_at,
                    'shift_details' => $shiftPolicy ? [
                        'shift_name' => $shiftPolicy->shift_name,
                        'shift_start_time' => $shiftPolicy->shift_start_time,
                        'second_half' => $shiftPolicy->second_half,
                        'puid' => $shiftPolicy->puid
                    ] : null
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Company shift policies retrieved successfully.',
                'count' => $result->count(),
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching company shift policies: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch company shift policies by corp_id
     *
     * @param string $corp_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchByCorpId($corp_id)
    {
        try {
            if (empty($corp_id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Corp ID is required'
                ], 400);
            }

            $companyShiftPolicies = CompanyShiftPolicy::where('corp_id', $corp_id)->get();

            if ($companyShiftPolicies->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No company shift policies found for this corp_id.',
                    'corp_id' => $corp_id,
                    'data' => []
                ]);
            }

            // Fetch shift details for each company shift policy
            $result = $companyShiftPolicies->map(function ($companyShiftPolicy) {
                // Find shift policy details by matching corp_id and shift_code
                $shiftPolicy = ShiftPolicy::where('corp_id', $companyShiftPolicy->corp_id)
                    ->where('shift_code', $companyShiftPolicy->shift_code)
                    ->first();

                return [
                    'id' => $companyShiftPolicy->id,
                    'corp_id' => $companyShiftPolicy->corp_id,
                    'company_name' => $companyShiftPolicy->company_name,
                    'shift_code' => $companyShiftPolicy->shift_code,
                    'created_at' => $companyShiftPolicy->created_at,
                    'updated_at' => $companyShiftPolicy->updated_at,
                    'shift_details' => $shiftPolicy ? [
                        'shift_name' => $shiftPolicy->shift_name,
                        'shift_start_time' => $shiftPolicy->shift_start_time,
                        'second_half' => $shiftPolicy->second_half,
                        'puid' => $shiftPolicy->puid
                    ] : null
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Company shift policies retrieved successfully.',
                'corp_id' => $corp_id,
                'count' => $result->count(),
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching company shift policies: ' . $e->getMessage(),
                'corp_id' => $corp_id
            ], 500);
        }
    }
}
