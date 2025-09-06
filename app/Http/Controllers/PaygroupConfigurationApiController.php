<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaygroupConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PaygroupConfigurationApiController extends Controller
{
    // Add or Update (same GroupName can't be added for same corpId)
    public function storeOrUpdate(Request $request)
    {
        $data = $request->all();

        // Check for duplicate GroupName for the same corpId (excluding current puid if updating)
        $query = PaygroupConfiguration::where('corpId', $data['corpId'])
            ->where('GroupName', $data['GroupName']);
        if (isset($data['puid'])) {
            $query->where('puid', '!=', $data['puid']);
        }
        if ($query->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Duplicate GroupName for this corpId is not allowed.'
            ], 409);
        }

        // If puid exists, update; else, create
        $paygroup = PaygroupConfiguration::where('puid', $data['puid'] ?? '')->first();
        if ($paygroup) {
            $paygroup->update($data);
            $message = 'Paygroup configuration updated successfully.';
        } else {
            $paygroup = PaygroupConfiguration::create($data);
            $message = 'Paygroup configuration added successfully.';
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $paygroup
        ]);
    }

    // Fetch by corpId
    public function fetchByCorpId($corpId)
    {
        $data = PaygroupConfiguration::where('corpId', $corpId)->get();
        return response()->json(['data' => $data]);
    }

    // Fetch by puid
    public function fetchByPuid($puid)
    {
        $data = PaygroupConfiguration::where('puid', $puid)->first();
        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Paygroup configuration not found.',
                'data' => (object)[]
            ], 404);
        }
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // Delete by puid
    public function destroy($puid)
    {
        $deleted = PaygroupConfiguration::where('puid', $puid)->delete();
        if ($deleted) {
            return response()->json(['status' => true, 'message' => 'Deleted successfully']);
        } else {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }
    }

    // Fetch IncludedComponents as trimmed list by puid
    public function fetchIncludedComponents($puid)
    {
        $paygroup = PaygroupConfiguration::where('puid', $puid)->first();

        if (!$paygroup || empty($paygroup->IncludedComponents)) {
            return response()->json([
                'status' => false,
                'message' => 'No IncludedComponents found.',
                'data' => []
            ], 404);
        }

        // Split by comma, trim each value, and filter out empty strings
        $components = array_filter(array_map('trim', explode(',', $paygroup->IncludedComponents)));

        return response()->json([
            'status' => true,
            'data' => array_values($components)
        ]);
    }

    // Fetch GroupNames by Employment Details
    public function fetchGroupNamesByEmploymentDetails($corp_id, $EmpCode)
    {
        $employmentColumns = Schema::getColumnListing('employment_details');

        $paygroups = DB::table('paygroup_configurations')
            ->where('corpId', $corp_id)
            ->get();

        $employmentDetails = DB::table('employment_details')
            ->where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->first();

        if (!$employmentDetails) {
            return response()->json([
                'status' => false,
                'message' => 'Employment details not found.',
                'data' => []
            ], 404);
        }

        $result = [];

        foreach ($paygroups as $paygroup) {
            // Get field values directly
            $applicabilityTypeValue = $paygroup->ApplicabiltyType ?? $paygroup->ApplicabilityType ?? '';
            $advanceApplicabilityTypeValue = $paygroup->AdvanceApplicabilityType ?? '';
            $applicableOnValue = $paygroup->ApplicableOn ?? '';
            $advanceApplicableOnValue = $paygroup->AdvanceApplicableOn ?? '';

            // Process types and values
            $applicabilityTypes = $this->prepareTypes($applicabilityTypeValue, $employmentColumns);
            $advanceApplicabilityTypes = $this->prepareTypes($advanceApplicabilityTypeValue, $employmentColumns);
            $applicableOnValues = $this->prepareValues($applicableOnValue);
            $advanceApplicableOnValues = $this->prepareValues($advanceApplicableOnValue);

            // Check matches
            $applicableMatches = $this->checkMatch($applicabilityTypes, $applicableOnValues, $employmentDetails);
            $advanceApplicableMatches = $this->checkMatch($advanceApplicabilityTypes, $advanceApplicableOnValues, $employmentDetails);

            // Logic: if advance exists, both must match; if not, only applicable needs to match
            $hasAdvanceData = !empty(trim($advanceApplicableOnValue));
            
            if ($hasAdvanceData) {
                // Both ApplicableOn and AdvanceApplicableOn must match
                if ($applicableMatches && $advanceApplicableMatches) {
                    $result[] = $paygroup->GroupName;
                }
            } else {
                // Only ApplicableOn needs to match if AdvanceApplicableOn is null/empty
                if ($applicableMatches) {
                    $result[] = $paygroup->GroupName;
                }
            }
        }

        return response()->json([
            'status' => true,
            'data' => array_values(array_unique($result))
        ]);
    }

    private function prepareTypes($typeString, $employmentColumns)
    {
        $types = [];
        foreach (explode(',', $typeString ?? '') as $type) {
            $type = str_replace(' ', '', trim($type)); // Remove spaces but don't convert to lowercase
            
            // Handle specific mappings
            if (strtolower($type) === 'company') {
                $type = 'company_name';
            }
            
            if ($type && in_array($type, $employmentColumns)) {
                $types[] = $type;
            }
        }
        return $types;
    }

    private function prepareValues($valueString)
    {
        return array_filter(array_map(fn($v) => trim($v), explode(',', $valueString ?? ''))); // Keep original case and spaces
    }

    private function checkMatch($columns, $values, $employmentDetails)
    {
        if (empty($columns) || empty($values)) {
            return false;
        }
        
        // Check if at least one column-value pair matches
        foreach ($columns as $col) {
            if (isset($employmentDetails->$col)) {
                $empValue = trim($employmentDetails->$col); // Keep original case
                if (in_array($empValue, $values, true)) {
                    return true; // Found a match
                }
            }
        }
        return false; // No matches found
    }

    // Fetch Gross with calculated values (with basic salary as URL parameter)
    public function fetchGrossByGroupName($groupName, $basicSalary)
    {
        // Validate basic salary
        if (!is_numeric($basicSalary) || $basicSalary < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Valid basic salary is required.',
                'data' => []
            ], 400);
        }

        // Get IncludedComponents from paygroup_configurations
        $paygroup = DB::table('paygroup_configurations')
            ->where('GroupName', $groupName)
            ->first();

        if (!$paygroup || empty($paygroup->IncludedComponents)) {
            return response()->json([
                'status' => false,
                'message' => 'GroupName not found or no IncludedComponents available.',
                'data' => []
            ], 404);
        }

        // Split IncludedComponents by comma and trim each value
        $includedComponents = array_filter(array_map('trim', explode(',', $paygroup->IncludedComponents)));

        $result = [];
        $totalGrossMonthly = 0;
        $totalGrossAnnual = 0;

        foreach ($includedComponents as $componentName) {
            // Fetch from pay_components with conditions for Addition OR Addition & Deduction
            $payComponent = DB::table('pay_components')
                ->where('componentName', $componentName)
                ->where('isPartOfCtcYn', 1)
                ->where(function($query) {
                    $query->where('payType', 'Addition')
                          ->orWhere('payType', 'Addition & Deduction');
                })
                ->first();

            if ($payComponent) {
                $calculatedValue = 0.0;
                $formula = null;

                // Check if component name is "Basic" (case-insensitive)
                if (strtolower(trim($componentName)) === 'basic') {
                    $calculatedValue = (float)$basicSalary;
                    $formula = 'Basic';
                } else {
                    $calculationResult = $this->calculateComponentValue($componentName, $basicSalary);
                    $calculatedValue = $calculationResult['calculatedValue'];
                    $formula = $calculationResult['formula'];
                }

                // ✅ **NEW:** Calculate annual value
                $annualCalculatedValue = $calculatedValue > 0 ? $calculatedValue * 12 : 0;

                $componentResult = [
                    'componentName' => $componentName,
                    'payType' => $payComponent->payType,
                    'paymentNature' => $payComponent->paymentNature,
                    'formula' => $formula,
                    'calculatedValue' => $calculatedValue,
                    'annualCalculatedValue' => $annualCalculatedValue
                ];

                $result[] = $componentResult;
                $totalGrossMonthly += $calculatedValue;
                $totalGrossAnnual += $annualCalculatedValue;
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'groupName' => $groupName,
                'basicSalary' => (float)$basicSalary,
                'components' => $result,
                'totalGross' => [
                    'monthly' => $totalGrossMonthly,
                    'annual' => $totalGrossAnnual
                ]
            ]
        ]);
    }

    /**
     * FIXED HELPER METHOD for calculating component value based on the formula_builders logic.
     */
    private function calculateComponentValue($componentName, $basicSalary)
    {
        $formulaBuilder = DB::table('formula_builders')
            ->where('componentName', $componentName)
            ->first();

        // If no formula is defined for the component, return defaults.
        if (!$formulaBuilder) {
            return ['calculatedValue' => 0.0, 'formula' => 'N/A'];
        }

        $formulaType = $formulaBuilder->formula ?? null;
        $refersTo = $formulaBuilder->componentNameRefersTo ?? null;
        $percentage = (float)($formulaBuilder->referenceValue ?? 0);
        $calculatedValue = 0.0;
        $formula = $formulaType; // Default to formula type

        // ✅ **FIXED:** Handle different formula types properly
        if (strtolower($formulaType) === 'percent') {
            // For percentage-based calculations
            // Handle case where componentNameRefersTo might be 'BASIC' or 'Basic'
            if (strtolower($refersTo ?? '') === 'basic' && $percentage > 0) {
                $calculatedValue = ($percentage / 100) * (float)$basicSalary;
                $formula = $percentage . '% of Basic'; // ✅ Return descriptive formula
            } else {
                $calculatedValue = 0.0;
                $formula = $percentage . '% of ' . ($refersTo ?? 'Unknown');
            }
        } elseif (strtolower($formulaType) === 'fixed') {
            // For fixed amount (referenceValue contains the fixed amount)
            $calculatedValue = $percentage; // referenceValue is the fixed amount
            $formula = 'Fixed: ₹' . number_format($percentage, 2);
        } elseif (strtolower($formulaType) === 'variable') {
            // Variable amounts are manually entered
            $calculatedValue = 0.0;
            $formula = 'Variable';
        } else {
            // Unknown formula type
            $calculatedValue = 0.0;
            $formula = 'Unknown Formula Type: ' . $formulaType;
        }

        return [
            'calculatedValue' => round($calculatedValue, 2), 
            'formula' => $formula
        ];
    }


    // Fetch OtherBenefitsAllowances by GroupName and corpId
    public function fetchOtherBenefitsAllowances($groupName, $corpId, $basicSalary = 0)
    {
        // Get both IncludedComponents and CTCAllowances from paygroup_configurations
        $paygroup = DB::table('paygroup_configurations')
            ->where('GroupName', $groupName)
            ->where('corpId', $corpId)
            ->first();

        if (!$paygroup) {
            return response()->json([
                'status' => false,
                'message' => 'GroupName not found for this corpId.',
                'data' => []
            ], 404);
        }

        $result = [];
        $totalBenefitsMonthly = 0;
        $totalBenefitsAnnual = 0;

        // Process IncludedComponents for "Benefits" payType
        if (!empty($paygroup->IncludedComponents)) {
            $includedComponents = array_filter(array_map('trim', explode(',', $paygroup->IncludedComponents)));
            
            foreach ($includedComponents as $componentName) {
                $payComponent = DB::table('pay_components')
                    ->where('componentName', $componentName)
                    ->where('payType', 'Benefits')
                    ->first();

                if ($payComponent) {
                    $calculationResult = $this->calculateComponentValue($componentName, $basicSalary);
                    $calculatedValue = $calculationResult['calculatedValue'];
                    $formula = $calculationResult['formula'];
                    $annualCalculatedValue = $calculatedValue > 0 ? $calculatedValue * 12 : 0;

                    $benefitResult = [
                        'componentName' => $payComponent->componentName,
                        'payType' => $payComponent->payType,
                        'paymentNature' => $payComponent->paymentNature ?? null,
                        'isPartOfCtcYn' => $payComponent->isPartOfCtcYn ?? null,
                        'componentDescription' => $payComponent->componentDescription ?? null,
                        'formula' => $formula,
                        'calculatedValue' => $calculatedValue,
                        'annualCalculatedValue' => $annualCalculatedValue
                    ];

                    $result[] = $benefitResult;
                    $totalBenefitsMonthly += $calculatedValue;
                    $totalBenefitsAnnual += $annualCalculatedValue;
                }
            }
        }

        // Process CTCAllowances (existing logic)
        if (!empty($paygroup->CTCAllowances)) {
            $ctcAllowances = array_filter(array_map('trim', explode(',', $paygroup->CTCAllowances)));

            foreach ($ctcAllowances as $allowanceName) {
                $payComponent = DB::table('pay_components')
                    ->where('componentName', $allowanceName)
                    ->first();

                $calculatedValue = 0.0;
                $formula = 'Manual Entry';
                $annualCalculatedValue = 0.0;

                if ($payComponent) {
                    $allowanceResult = [
                        'componentName' => $payComponent->componentName,
                        'payType' => $payComponent->payType ?? 'CTC Allowance',
                        'paymentNature' => $payComponent->paymentNature ?? null,
                        'isPartOfCtcYn' => $payComponent->isPartOfCtcYn ?? null,
                        'componentDescription' => $payComponent->componentDescription ?? null,
                        'formula' => $formula,
                        'calculatedValue' => $calculatedValue,
                        'annualCalculatedValue' => $annualCalculatedValue
                    ];
                } else {
                    $allowanceResult = [
                        'componentName' => $allowanceName,
                        'payType' => 'CTC Allowance',
                        'paymentNature' => null,
                        'isPartOfCtcYn' => null,
                        'componentDescription' => null,
                        'formula' => $formula,
                        'calculatedValue' => $calculatedValue,
                        'annualCalculatedValue' => $annualCalculatedValue
                    ];
                }

                $result[] = $allowanceResult;
                $totalBenefitsMonthly += $calculatedValue;
                $totalBenefitsAnnual += $annualCalculatedValue;
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'groupName' => $groupName,
                'corpId' => $corpId,
                'basicSalary' => (float)$basicSalary,
                'otherBenefitsAllowances' => $result,
                'totalBenefits' => [
                    'monthly' => $totalBenefitsMonthly,
                    'annual' => $totalBenefitsAnnual
                ],
                'totalCount' => count($result)
            ]
        ]);
    }

    // Fetch Deductions with calculated values
    public function fetchDeductionsByGroupName($groupName, $basicSalary)
    {
        // Validate basic salary
        if (!is_numeric($basicSalary) || $basicSalary < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Valid basic salary is required.',
                'data' => []
            ], 400);
        }

        // Get IncludedComponents from paygroup_configurations
        $paygroup = DB::table('paygroup_configurations')
            ->where('GroupName', $groupName)
            ->first();

        if (!$paygroup || empty($paygroup->IncludedComponents)) {
            return response()->json([
                'status' => false,
                'message' => 'GroupName not found or no IncludedComponents available.',
                'data' => []
            ], 404);
        }

        // Split IncludedComponents by comma and trim each value
        $includedComponents = array_filter(array_map('trim', explode(',', $paygroup->IncludedComponents)));

        $result = [];
        $totalDeductionsMonthly = 0;
        $totalDeductionsAnnual = 0;

        foreach ($includedComponents as $componentName) {
            // Fetch from pay_components with conditions for Deduction OR Addition & Deduction
            $payComponent = DB::table('pay_components')
                ->where('componentName', $componentName)
                ->where('isPartOfCtcYn', 1)
                ->where(function($query) {
                    $query->where('payType', 'Deduction')
                          ->orWhere('payType', 'Addition & Deduction');
                })
                ->first();

            if ($payComponent) {
                $calculationResult = $this->calculateComponentValue($componentName, $basicSalary);
                $calculatedValue = $calculationResult['calculatedValue'];
                $formula = $calculationResult['formula'];

                // ✅ **NEW:** Calculate annual value
                $annualCalculatedValue = $calculatedValue > 0 ? $calculatedValue * 12 : 0;

                $componentResult = [
                    'componentName' => $componentName,
                    'payType' => $payComponent->payType,
                    'paymentNature' => $payComponent->paymentNature,
                    'formula' => $formula,
                    'calculatedValue' => $calculatedValue,
                    'annualCalculatedValue' => $annualCalculatedValue
                ];

                $result[] = $componentResult;
                $totalDeductionsMonthly += $calculatedValue;
                $totalDeductionsAnnual += $annualCalculatedValue;
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'groupName' => $groupName,
                'basicSalary' => (float)$basicSalary,
                'deductions' => $result,
                'totalDeductions' => [
                    'monthly' => $totalDeductionsMonthly,
                    'annual' => $totalDeductionsAnnual
                ]
            ]
        ]);
    }

    // ✅ **NEW METHOD:** Fetch complete payroll breakdown with all components
    public function fetchCompletePayrollBreakdown($groupName, $corpId, $basicSalary)
    {
        // Validate basic salary
        if (!is_numeric($basicSalary) || $basicSalary < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Valid basic salary is required.',
                'data' => []
            ], 400);
        }

        // Get all three categories
        $grossResponse = $this->fetchGrossByGroupName($groupName, $basicSalary);
        $deductionsResponse = $this->fetchDeductionsByGroupName($groupName, $basicSalary);
        $benefitsResponse = $this->fetchOtherBenefitsAllowances($groupName, $corpId, $basicSalary);

        // Extract data from responses
        $grossData = $grossResponse->getData(true)['data'] ?? [];
        $deductionsData = $deductionsResponse->getData(true)['data'] ?? [];
        $benefitsData = $benefitsResponse->getData(true)['data'] ?? [];

        // Calculate net salary
        $grossMonthly = $grossData['totalGross']['monthly'] ?? 0;
        $grossAnnual = $grossData['totalGross']['annual'] ?? 0;
        $deductionsMonthly = $deductionsData['totalDeductions']['monthly'] ?? 0;
        $deductionsAnnual = $deductionsData['totalDeductions']['annual'] ?? 0;
        $benefitsMonthly = $benefitsData['totalBenefits']['monthly'] ?? 0;
        $benefitsAnnual = $benefitsData['totalBenefits']['annual'] ?? 0;

        $netSalaryMonthly = $grossMonthly - $deductionsMonthly;
        $netSalaryAnnual = $grossAnnual - $deductionsAnnual;
        $totalCTCMonthly = $grossMonthly + $benefitsMonthly;
        $totalCTCAnnual = $grossAnnual + $benefitsAnnual;

        return response()->json([
            'status' => true,
            'data' => [
                'groupName' => $groupName,
                'corpId' => $corpId,
                'basicSalary' => (float)$basicSalary,
                'gross' => $grossData['components'] ?? [],
                'deductions' => $deductionsData['deductions'] ?? [],
                'otherBenefitsAllowances' => $benefitsData['otherBenefitsAllowances'] ?? [],
                'summary' => [
                    'totalGross' => [
                        'monthly' => $grossMonthly,
                        'annual' => $grossAnnual
                    ],
                    'totalDeductions' => [
                        'monthly' => $deductionsMonthly,
                        'annual' => $deductionsAnnual
                    ],
                    'totalBenefits' => [
                        'monthly' => $benefitsMonthly,
                        'annual' => $benefitsAnnual
                    ],
                    'netSalary' => [
                        'monthly' => $netSalaryMonthly,
                        'annual' => $netSalaryAnnual
                    ],
                    'totalCTC' => [
                        'monthly' => $totalCTCMonthly,
                        'annual' => $totalCTCAnnual
                    ]
                ]
            ]
        ]);
    }

    /**
     * DEBUG METHOD: Check what's in formula_builders table
     */
    public function debugFormulaBuilder($componentName = 'HRA')
    {
        $specificComponent = DB::table('formula_builders')
            ->where('componentName', $componentName)
            ->first();

        $allComponents = DB::table('formula_builders')
            ->select('componentName', 'formula', 'componentNameRefersTo', 'referenceValue')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'searchedComponent' => $componentName,
                'componentData' => $specificComponent,
                'allComponents' => $allComponents,
                'testCalculation' => $specificComponent ? $this->calculateComponentValue($componentName, 10000) : 'Component not found'
            ]
        ]);
    }

}
