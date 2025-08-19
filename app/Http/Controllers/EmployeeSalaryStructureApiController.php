<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeSalaryStructure;

class EmployeeSalaryStructureApiController extends Controller
{
    // Add or Update Employee Salary Structure
    public function storeOrUpdate(Request $request)
    {
        try {
            $request->validate([
                'corpId' => 'required|string|max:10',
                'puid' => 'required|string|max:50',
                'empCode' => 'required|string|max:20',
                'companyName' => 'required|string|max:50',
                'salaryRevisionMonth' => 'required|string|max:11',
                'arrearWithEffectFrom' => 'required|string|max:11',
                'payGroup' => 'required|string|max:20',
                'ctc' => 'required|string|max:20',
                'ctcYearly' => 'required|string|max:20',
                'monthlyBasic' => 'required|string|max:20',
                'leaveEnchashOnGross' => 'required|string|max:20',
                'grossList' => 'required|string|max:255',
            ]);

            $data = $request->all();

            $salaryStructure = EmployeeSalaryStructure::updateOrCreate(
                ['corpId' => $data['corpId'], 'puid' => $data['puid']],
                $data
            );

            $status = $salaryStructure->wasRecentlyCreated ? 'created' : 'updated';

            return response()->json([
                'status' => true,
                'message' => "Employee salary structure {$status} successfully",
                'data' => $salaryStructure
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // Fetch by empCode, companyName, and corpId
    public function fetchByEmpDetails($empCode, $companyName, $corpId)
    {
        try {
            $salaryStructure = EmployeeSalaryStructure::where('empCode', $empCode)
                ->where('companyName', $companyName)
                ->where('corpId', $corpId)
                ->first();

            if (!$salaryStructure) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee salary structure not found',
                    'data' => (object)[]
                ], 404);
            }

            // Prepare response with JSON parsed fields
            $responseData = $salaryStructure->toArray();
            
            // Parse JSON strings to arrays with JSON objects inside using square brackets
            $responseData['grossList'] = [
                $this->parseJsonField($salaryStructure->grossList)
            ];
            $responseData['otherBenifits'] = [
                $this->parseJsonField($salaryStructure->otherBenifits)
            ];
            $responseData['recurringDeductions'] = [
                $this->parseJsonField($salaryStructure->recurringDeductions)
            ];

            return response()->json([
                'status' => true,
                'message' => 'Employee salary structure fetched successfully',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete by puid
    public function destroy($puid)
    {
        try {
            $salaryStructure = EmployeeSalaryStructure::where('puid', $puid)->first();
            
            if (!$salaryStructure) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee salary structure not found'
                ], 404);
            }

            $salaryStructure->delete();

            return response()->json([
                'status' => true,
                'message' => 'Employee salary structure deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper method to parse JSON fields
    private function parseJsonField($field)
    {
        if (empty($field)) {
            return null;
        }
        
        // If it's already an array (from cast), return as is
        if (is_array($field)) {
            return $field;
        }
        
        // Try to decode JSON string
        $decoded = json_decode($field, true);
        return $decoded !== null ? $decoded : $field;
    }
}
