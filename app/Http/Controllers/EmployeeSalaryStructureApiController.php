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

    /**
     * ✅ Salary Revision Process API
     * Fetches previous year data, distributes increment proportionally, and creates new salary structure
     */
    public function salaryRevisionProcess(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'corpId' => 'required|string|max:10',
                'companyName' => 'required|string|max:100',
                'empCode' => 'required|string|max:20',
                'year' => 'required|string|max:4',
                'salaryRevisionMonth' => 'required|string|max:11',
                'arrearWithEffectFrom' => 'required|string|max:11',
                'increment' => 'required|numeric|min:0'
            ]);

            $corpId = $request->corpId;
            $companyName = $request->companyName;
            $empCode = $request->empCode;
            $currentYear = $request->year;
            $salaryRevisionMonth = $request->salaryRevisionMonth;
            $arrearWithEffectFrom = $request->arrearWithEffectFrom;
            $incrementAmount = (float)$request->increment;

            // Calculate previous year
            $previousYear = (int)$currentYear - 1;

            // Fetch previous year salary structure
            $previousSalary = EmployeeSalaryStructure::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('empCode', $empCode)
                ->where('year', (string)$previousYear)
                ->first();

            if (!$previousSalary) {
                return response()->json([
                    'status' => false,
                    'message' => "No salary structure found for employee {$empCode} in year {$previousYear}",
                    'data' => []
                ], 404);
            }

            // Parse JSON fields from previous year
            $previousGrossList = $this->parseJsonField($previousSalary->grossList);
            $previousOtherBenefits = $this->parseJsonField($previousSalary->otherBenifits);
            $previousRecurringDeductions = $this->parseJsonField($previousSalary->recurringDeductions);

            // Calculate previous totals
            $previousTotals = $this->calculateSalaryTotals(
                $previousGrossList,
                $previousOtherBenefits,
                $previousRecurringDeductions
            );

            // Get previous basic salary and CTC
            $previousBasicSalary = (float)$previousSalary->monthlyBasic;
            $previousCTC = (float)$previousSalary->ctc;

            // Calculate new CTC (add increment to previous CTC)
            $newCTC = $previousCTC + $incrementAmount;
            $incrementPercentage = ($incrementAmount / $previousCTC) * 100;

            // Calculate the proportion of each component in old CTC
            $componentProportions = $this->calculateComponentProportions(
                $previousGrossList,
                $previousOtherBenefits,
                $previousCTC
            );

            // Distribute new CTC proportionally to gross components
            $newGrossList = $this->distributeCtcToGrossComponents(
                $previousGrossList,
                $componentProportions,
                $newCTC
            );

            // Calculate new basic salary from gross list
            $newBasicSalary = 0;
            foreach ($newGrossList as $component) {
                if (strtolower($component['componentName']) === 'basic') {
                    $newBasicSalary = $component['calculatedValue'];
                    break;
                }
            }

            // Recalculate benefits and deductions based on new basic salary
            $newOtherBenefits = $this->recalculateBenefits(
                $previousOtherBenefits,
                $previousBasicSalary,
                $newBasicSalary,
                $componentProportions
            );

            $newRecurringDeductions = $this->recalculateDeductions(
                $previousRecurringDeductions,
                $previousBasicSalary,
                $newBasicSalary
            );

            // Calculate new totals
            $newTotals = $this->calculateSalaryTotals(
                $newGrossList,
                $newOtherBenefits,
                $newRecurringDeductions
            );

            // Calculate new CTC Yearly
            $newCTCYearly = $newCTC * 12;

            // Generate new PUID
            $newPuid = $this->generatePuid($corpId, $empCode, $currentYear);

            // Prepare new salary structure data
            $newSalaryData = [
                'corpId' => $corpId,
                'puid' => $newPuid,
                'empCode' => $empCode,
                'companyName' => $companyName,
                'salaryRevisionMonth' => $salaryRevisionMonth,
                'arrearWithEffectFrom' => $arrearWithEffectFrom,
                'payGroup' => $previousSalary->payGroup,
                'ctc' => round($newCTC, 0),
                'ctcYearly' => round($newCTCYearly, 0),
                'monthlyBasic' => round($newBasicSalary, 0),
                'leaveEnchashOnGross' => $previousSalary->leaveEnchashOnGross,
                'grossList' => json_encode($newGrossList),
                'otherBenifits' => json_encode($newOtherBenefits),
                'recurringDeductions' => json_encode($newRecurringDeductions),
                'year' => $currentYear,
                'increment' => round($incrementAmount, 0)
            ];

            // Insert new salary structure
            $newSalaryStructure = EmployeeSalaryStructure::create($newSalaryData);

            // Prepare detailed response
            return response()->json([
                'status' => true,
                'message' => 'Salary revision processed successfully',
                'data' => [
                    'previousYear' => $previousYear,
                    'currentYear' => $currentYear,
                    'previousSalary' => [
                        'basicSalary' => $previousBasicSalary,
                        'ctc' => $previousCTC,
                        'monthlyGross' => $previousTotals['monthlyGross'],
                        'annualGross' => $previousTotals['annualGross']
                    ],
                    'revision' => [
                        'incrementAmount' => $incrementAmount,
                        'incrementPercentage' => round($incrementPercentage, 2),
                        'revisionMonth' => $salaryRevisionMonth,
                        'effectiveFrom' => $arrearWithEffectFrom
                    ],
                    'newSalary' => [
                        'puid' => $newPuid,
                        'basicSalary' => round($newBasicSalary, 0),
                        'ctc' => round($newCTC, 0),
                        'ctcYearly' => round($newCTCYearly, 0),
                        'monthlyGross' => $newTotals['monthlyGross'],
                        'annualGross' => $newTotals['annualGross'],
                        'monthlyDeductions' => $newTotals['monthlyDeduction'],
                        'annualDeductions' => $newTotals['annualDeduction'],
                        'monthlyAllowances' => $newTotals['monthlyAllowance'],
                        'annualAllowances' => $newTotals['annualAllowance'],
                        'monthlyNetSalary' => $newTotals['monthlyNetSalary'],
                        'annualNetSalary' => $newTotals['annualNetSalary'],
                        'grossList' => $newGrossList,
                        'otherBenefits' => $newOtherBenefits,
                        'recurringDeductions' => $newRecurringDeductions
                    ],
                    'savedRecord' => $newSalaryStructure
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing salary revision: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Calculate proportion of each component in CTC
     */
    private function calculateComponentProportions($grossList, $otherBenefits, $ctc)
    {
        $proportions = [];
        
        if ($ctc <= 0) {
            return $proportions;
        }

        // Calculate proportions for gross components
        foreach ($grossList as $component) {
            $componentName = $component['componentName'] ?? '';
            $value = (float)($component['calculatedValue'] ?? 0);
            $proportions[$componentName] = $value / $ctc;
        }

        // Calculate proportions for benefits that are part of CTC
        foreach ($otherBenefits as $component) {
            $componentName = $component['componentName'] ?? '';
            $value = (float)($component['calculatedValue'] ?? 0);
            $isPartOfCtc = $component['isPartOfCtcYn'] ?? 0;
            
            if ($isPartOfCtc == 1) {
                $proportions[$componentName] = $value / $ctc;
            }
        }

        return $proportions;
    }

    /**
     * Distribute new CTC to gross components proportionally
     */
    private function distributeCtcToGrossComponents($grossList, $proportions, $newCTC)
    {
        $newGrossList = [];
        
        foreach ($grossList as $component) {
            $newComponent = $component;
            $componentName = $component['componentName'] ?? '';
            $formula = $component['formula'] ?? '';
            
            // First pass: distribute CTC proportionally to get initial values
            if (isset($proportions[$componentName])) {
                $newValue = round($newCTC * $proportions[$componentName], 0);
                $newComponent['calculatedValue'] = $newValue;
                $newComponent['annualCalculatedValue'] = $newValue * 12;
            }
            
            $newGrossList[] = $newComponent;
        }

        // Second pass: recalculate formula-based components based on new Basic
        $newBasic = 0;
        foreach ($newGrossList as $component) {
            if (strtolower($component['componentName']) === 'basic') {
                $newBasic = $component['calculatedValue'];
                break;
            }
        }

        if ($newBasic > 0) {
            foreach ($newGrossList as $index => $component) {
                $formula = $component['formula'] ?? '';
                
                // Recalculate percentage-based formulas
                if (strpos(strtolower($formula), '% of basic') !== false) {
                    preg_match('/(\d+\.?\d*)%/', $formula, $matches);
                    if (isset($matches[1])) {
                        $percentage = (float)$matches[1];
                        $newValue = round(($newBasic * $percentage) / 100, 0);
                        $newGrossList[$index]['calculatedValue'] = $newValue;
                        $newGrossList[$index]['annualCalculatedValue'] = $newValue * 12;
                    }
                }
            }
        }

        return $newGrossList;
    }

    /**
     * Recalculate benefits based on new basic salary
     */
    private function recalculateBenefits($benefitsList, $oldBasic, $newBasic, $proportions)
    {
        $newBenefits = [];

        foreach ($benefitsList as $component) {
            $newComponent = $component;
            $componentName = $component['componentName'] ?? '';
            $formula = $component['formula'] ?? '';
            $oldValue = (float)($component['calculatedValue'] ?? 0);

            // Recalculate based on formula
            if (strpos(strtolower($formula), '% of basic') !== false) {
                // Extract percentage from formula
                preg_match('/(\d+\.?\d*)%/', $formula, $matches);
                if (isset($matches[1])) {
                    $percentage = (float)$matches[1];
                    $newValue = round(($newBasic * $percentage) / 100, 0);
                    $newComponent['calculatedValue'] = $newValue;
                    $newComponent['annualCalculatedValue'] = $newValue * 12;
                }
            } elseif (strtolower($formula) === 'n/a' || strtolower($formula) === 'not applicable' || $formula === 'Manual Entry') {
                // For N/A or Manual Entry, keep proportional to CTC increase
                if ($oldBasic > 0 && $oldValue > 0) {
                    $incrementRatio = $newBasic / $oldBasic;
                    $newValue = round($oldValue * $incrementRatio, 0);
                    $newComponent['calculatedValue'] = $newValue;
                    $newComponent['annualCalculatedValue'] = $newValue * 12;
                }
            } else {
                // For other formulas, keep the value proportional
                if ($oldBasic > 0 && $oldValue > 0) {
                    $incrementRatio = $newBasic / $oldBasic;
                    $newValue = round($oldValue * $incrementRatio, 0);
                    $newComponent['calculatedValue'] = $newValue;
                    $newComponent['annualCalculatedValue'] = $newValue * 12;
                }
            }

            $newBenefits[] = $newComponent;
        }

        return $newBenefits;
    }

    /**
     * Recalculate deductions based on new basic salary
     */
    private function recalculateDeductions($deductionsList, $oldBasic, $newBasic)
    {
        $newDeductions = [];

        foreach ($deductionsList as $component) {
            $newComponent = $component;
            $componentName = $component['componentName'] ?? '';
            $formula = $component['formula'] ?? '';
            $oldValue = (float)($component['calculatedValue'] ?? 0);

            // Recalculate based on formula
            if (strpos(strtolower($formula), '% of basic') !== false) {
                // Extract percentage from formula
                preg_match('/(\d+\.?\d*)%/', $formula, $matches);
                if (isset($matches[1])) {
                    $percentage = (float)$matches[1];
                    $newValue = round(($newBasic * $percentage) / 100, 0);
                    $newComponent['calculatedValue'] = $newValue;
                    $newComponent['annualCalculatedValue'] = $newValue * 12;
                }
            } elseif (strtolower($formula) === 'n/a' || strtolower($formula) === 'not applicable' || $formula === 'Manual Entry') {
                // For statutory deductions with N/A formula, recalculate proportionally
                if ($oldBasic > 0 && $oldValue > 0) {
                    $incrementRatio = $newBasic / $oldBasic;
                    $newValue = round($oldValue * $incrementRatio, 0);
                    $newComponent['calculatedValue'] = $newValue;
                    $newComponent['annualCalculatedValue'] = $newValue * 12;
                }
            } else {
                // For other formulas, keep proportional
                if ($oldBasic > 0 && $oldValue > 0) {
                    $incrementRatio = $newBasic / $oldBasic;
                    $newValue = round($oldValue * $incrementRatio, 0);
                    $newComponent['calculatedValue'] = $newValue;
                    $newComponent['annualCalculatedValue'] = $newValue * 12;
                }
            }

            $newDeductions[] = $newComponent;
        }

        return $newDeductions;
    }

    /**
     * Generate unique PUID
     */
    private function generatePuid($corpId, $empCode, $year)
    {
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        return strtoupper($corpId) . '_' . $empCode . '_' . $year . '_' . $timestamp . '_' . $random;
    }
}
