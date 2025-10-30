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
                'grossList' => 'required|string',
                'year' => 'nullable|string|max:4',
                'increment' => 'nullable|string|max:20',
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

    // Fetch by empCode, companyName, corpId and optionally year
    public function fetchByEmpDetails(Request $request, $empCode, $companyName, $corpId)
    {
        try {
            $query = EmployeeSalaryStructure::where('empCode', $empCode)
                ->where('companyName', $companyName)
                ->where('corpId', $corpId);

            // Add year filter if provided
            if ($request->has('year') && !empty($request->year)) {
                $query->where('year', $request->year);
            }

            $salaryStructure = $query->first();

            if (!$salaryStructure) {
                $message = 'Employee salary structure not found';
                if ($request->has('year') && !empty($request->year)) {
                    $message .= ' for year ' . $request->year;
                }
                
                return response()->json([
                    'status' => false,
                    'message' => $message,
                    'data' => (object)[],
                    'filters_applied' => [
                        'empCode' => $empCode,
                        'companyName' => $companyName,
                        'corpId' => $corpId,
                        'year' => $request->year ?? 'not specified'
                    ]
                ], 404);
            }

            // Prepare response with JSON parsed fields
            $responseData = $salaryStructure->toArray();
            
            // Parse JSON strings to arrays
            $grossList = $this->parseJsonField($salaryStructure->grossList);
            $otherBenefits = $this->parseJsonField($salaryStructure->otherBenifits);
            $recurringDeductions = $this->parseJsonField($salaryStructure->recurringDeductions);

            $responseData['grossList'] = $grossList;
            $responseData['otherBenifits'] = $otherBenefits;
            $responseData['recurringDeductions'] = $recurringDeductions;

            // ✅ **NEW:** Calculate totals
            $calculations = $this->calculateSalaryTotals(
                $grossList,
                $otherBenefits, 
                $recurringDeductions
            );

            // ✅ **NEW:** Add calculated totals to response
            $responseData['monthlyGross'] = $calculations['monthlyGross'];
            $responseData['annualGross'] = $calculations['annualGross'];
            $responseData['monthlyDeduction'] = $calculations['monthlyDeduction'];
            $responseData['annualDeduction'] = $calculations['annualDeduction'];
            $responseData['monthlyAllowance'] = $calculations['monthlyAllowance'];
            $responseData['annualAllowance'] = $calculations['annualAllowance'];
            $responseData['monthlyNetSalary'] = $calculations['monthlyNetSalary'];
            $responseData['annualNetSalary'] = $calculations['annualNetSalary'];

            $message = 'Employee salary structure fetched successfully';
            if ($request->has('year') && !empty($request->year)) {
                $message .= ' for year ' . $request->year;
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $responseData,
                'filters_applied' => [
                    'empCode' => $empCode,
                    'companyName' => $companyName,
                    'corpId' => $corpId,
                    'year' => $request->year ?? 'not specified'
                ]
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

    // ✅ **NEW METHOD:** Calculate salary totals
    private function calculateSalaryTotals($grossList, $otherBenefits, $recurringDeductions)
    {
        // Initialize totals
        $monthlyGross = 0.0;
        $monthlyAllowance = 0.0;
        $monthlyDeduction = 0.0;

        // Calculate gross total
        if (is_array($grossList)) {
            foreach ($grossList as $item) {
                if (isset($item['calculatedValue'])) {
                    $monthlyGross += (float)$item['calculatedValue'];
                }
            }
        }

        // Calculate other benefits/allowances total
        if (is_array($otherBenefits)) {
            foreach ($otherBenefits as $item) {
                if (isset($item['calculatedValue'])) {
                    $monthlyAllowance += (float)$item['calculatedValue'];
                }
            }
        }

        // Calculate deductions total
        if (is_array($recurringDeductions)) {
            foreach ($recurringDeductions as $item) {
                if (isset($item['calculatedValue'])) {
                    $monthlyDeduction += (float)$item['calculatedValue'];
                }
            }
        }

        // Calculate net salary
        $monthlyNetSalary = $monthlyGross - $monthlyDeduction;

        // Calculate annual values
        $annualGross = $monthlyGross * 12;
        $annualAllowance = $monthlyAllowance * 12;
        $annualDeduction = $monthlyDeduction * 12;
        $annualNetSalary = $monthlyNetSalary * 12;

        return [
            'monthlyGross' => round($monthlyGross, 2),
            'annualGross' => round($annualGross, 2),
            'monthlyDeduction' => round($monthlyDeduction, 2),
            'annualDeduction' => round($annualDeduction, 2),
            'monthlyAllowance' => round($monthlyAllowance, 2),
            'annualAllowance' => round($annualAllowance, 2),
            'monthlyNetSalary' => round($monthlyNetSalary, 2),
            'annualNetSalary' => round($annualNetSalary, 2)
        ];
    }
}
