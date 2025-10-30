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
                    $calculatedValue = round((float)$basicSalary, 0);
                    $formula = 'Basic';
                } else {
                    $calculationResult = $this->calculateComponentValue($componentName, $basicSalary);
                    $calculatedValue = round($calculationResult['calculatedValue'], 0);
                    $formula = $calculationResult['formula'];
                }

                // ✅ **NEW:** Calculate annual value with rounding
                $annualCalculatedValue = round($calculatedValue * 12, 0);

                $componentResult = [
                    'componentName' => $componentName,
                    'payType' => $payComponent->payType,
                    'paymentNature' => $payComponent->paymentNature,
                    'formula' => $formula,
                    'calculatedValue' => round($calculatedValue, 0),
                    'annualCalculatedValue' => round($annualCalculatedValue, 0)
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
            'calculatedValue' => round($calculatedValue, 0), 
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
                    $calculatedValue = round($calculationResult['calculatedValue'], 0);
                    $formula = $calculationResult['formula'];
                    $annualCalculatedValue = round($calculatedValue * 12, 0);

                    $benefitResult = [
                        'componentName' => $payComponent->componentName,
                        'payType' => $payComponent->payType,
                        'paymentNature' => $payComponent->paymentNature ?? null,
                        'isPartOfCtcYn' => $payComponent->isPartOfCtcYn ?? null,
                        'componentDescription' => $payComponent->componentDescription ?? null,
                        'formula' => $formula,
                        'calculatedValue' => round($calculatedValue, 0),
                        'annualCalculatedValue' => round($annualCalculatedValue, 0)
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
                $calculatedValue = round($calculationResult['calculatedValue'], 0);
                $formula = $calculationResult['formula'];

                // ✅ **NEW:** Calculate annual value with rounding
                $annualCalculatedValue = round($calculatedValue * 12, 0);

                $componentResult = [
                    'componentName' => $componentName,
                    'payType' => $payComponent->payType,
                    'paymentNature' => $payComponent->paymentNature,
                    'formula' => $formula,
                    'calculatedValue' => round($calculatedValue, 0),
                    'annualCalculatedValue' => round($annualCalculatedValue, 0)
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

   
    /**
     * DEBUG METHOD: Check database tables for statutory data
     */
    public function debugStatutoryData($corpId, $empCode, $companyName)
    {
        try {
            // First, let's see what columns exist in the table
            $statutoryColumns = DB::select("SHOW COLUMNS FROM employee_statutory_details");
            $esiColumns = DB::select("SHOW COLUMNS FROM esi");

            // Try different possible column name combinations
            $employeeStatutory = null;
            $queryUsed = '';
            
            // Common column name variations to try
            $corpIdVariations = ['corpId', 'corp_id', 'CorpId', 'CORP_ID'];
            $empCodeVariations = ['empCode', 'emp_code', 'EmpCode', 'EMP_CODE'];
            
            foreach ($corpIdVariations as $corpCol) {
                foreach ($empCodeVariations as $empCol) {
                    try {
                        $test = DB::table('employee_statutory_details')
                            ->whereRaw("$corpCol = ?", [$corpId])
                            ->whereRaw("$empCol = ?", [$empCode])
                            ->first();
                        
                        if ($test) {
                            $employeeStatutory = $test;
                            $queryUsed = "corpId column: $corpCol, empCode column: $empCol";
                            break 2;
                        }
                    } catch (\Exception $e) {
                        // Continue trying other combinations
                        continue;
                    }
                }
            }

            // If still not found, get a sample record to see the structure
            $sampleRecord = DB::table('employee_statutory_details')->first();

            return response()->json([
                'status' => true,
                'debug_data' => [
                    'search_params' => [
                        'corpId' => $corpId,
                        'empCode' => $empCode,
                        'companyName' => $companyName
                    ],
                    'employee_statutory_details' => [
                        'table_columns' => array_column($statutoryColumns, 'Field'),
                        'sample_record' => $sampleRecord,
                        'specific_employee' => $employeeStatutory,
                        'query_used' => $queryUsed
                    ],
                    'esi_table' => [
                        'table_columns' => array_column($esiColumns, 'Field')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * ✅ **FIXED:** Calculate statutory deductions with proper logic - NO DUPLICATES
     */
    private function calculateStatutoryDeductions($corpId, $empCode, $companyName, $basicSalary, $ctc)
    {
        $statutoryDeductions = [];
        
        try {
            // Try different column name combinations
            $employeeStatutory = null;
            $corpIdVariations = ['corpId', 'corp_id', 'CorpId', 'CORP_ID'];
            $empCodeVariations = ['empCode', 'emp_code', 'EmpCode', 'EMP_CODE'];
            
            foreach ($corpIdVariations as $corpCol) {
                foreach ($empCodeVariations as $empCol) {
                    try {
                        $test = DB::table('employee_statutory_details')
                            ->whereRaw("$corpCol = ?", [$corpId])
                            ->whereRaw("$empCol = ?", [$empCode])
                            ->first();
                        
                        if ($test) {
                            $employeeStatutory = $test;
                            break 2;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            Log::info("Employee Statutory Query Result:", [
                'corpId' => $corpId,
                'empCode' => $empCode,
                'found' => $employeeStatutory ? 'YES' : 'NO',
                'data' => $employeeStatutory
            ]);

            if (!$employeeStatutory) {
                Log::warning("No statutory details found");
                return $this->getDefaultStatutoryDeductions();
            }

            // Get all needed values
            $voluntaryPfYN = $employeeStatutory->VoluntaryPfYN ?? $employeeStatutory->voluntary_pf_yn ?? 0;
            $voluntaryPfPercent = $employeeStatutory->VoluntaryPFPercent ?? 
                                 $employeeStatutory->VouluntaryPFPercent ?? 
                                 $employeeStatutory->voluntary_pf_percent ?? 
                                 $employeeStatutory->vouluntary_pf_percent ?? 0;
            
            // ✅ FIXED: Check both EmployerCtbToNPSYN and EmployerCtbnToNPSYN (lowercase 'n')
            $employerCtbToNPS = $employeeStatutory->EmployerCtbnToNPSYN ?? 
                               $employeeStatutory->EmployerCtbToNPSYN ?? 
                               $employeeStatutory->employer_ctb_to_nps_yn ?? 
                               $employeeStatutory->employer_ctbn_to_nps_yn ?? 0;
            $empStateInsurance = $employeeStatutory->EmpStateInsuranceYN ?? $employeeStatutory->emp_state_insurance_yn ?? 0;
            $employerPercentage = $employeeStatutory->EmployerPercentage ?? $employeeStatutory->employer_percentage ?? 0;

            Log::info("Statutory Values:", [
                'voluntaryPfYN' => $voluntaryPfYN,
                'voluntaryPfPercent' => $voluntaryPfPercent,
                'employerCtbToNPS' => $employerCtbToNPS,
                'empStateInsurance' => $empStateInsurance,
                'employerPercentage' => $employerPercentage
            ]);

            // ✅ 1. VPF Calculation
            $vpfAmount = 0;
            $vpfFormula = 'Not Applicable';
            $vpfPercentage = 0;
            
            if ($voluntaryPfYN == 1 && $voluntaryPfPercent > 0) {
                $vpfPercentage = round((float)$voluntaryPfPercent, 2);
                $vpfAmount = round(($basicSalary * $vpfPercentage) / 100, 0);
                $vpfFormula = "{$vpfPercentage}% of Basic";
                
                Log::info("✅ VPF Calculated:", [
                    'percentage' => $vpfPercentage,
                    'amount' => $vpfAmount
                ]);
            }

            $statutoryDeductions[] = [
                'componentName' => 'Employee VPF',
                'payType' => 'Statutory Deduction',
                'paymentNature' => 'Monthly',
                'formula' => $vpfFormula,
                'percentage' => round($vpfPercentage, 2),
                'calculatedValue' => round($vpfAmount, 0),
                'annualCalculatedValue' => round($vpfAmount * 12, 0),
                'isStatutory' => true
            ];

            // ✅ 2. NPS Calculation - Only if ESI is FALSE
            $npsAmount = 0;
            $npsFormula = 'Not Applicable';
            $npsPercentage = 0;
            
            if ($empStateInsurance == 0) { // Only calculate NPS if ESI is false
                if ($employerCtbToNPS == 1 && $employerPercentage > 0) {
                    $npsPercentage = round((float)$employerPercentage, 2);
                    $npsAmount = round(($basicSalary * $npsPercentage) / 100, 0);
                    $npsFormula = "{$npsPercentage}% of Basic";
                    
                    Log::info("✅ NPS Calculated (ESI is false, NPS is true):", [
                        'percentage' => $npsPercentage,
                        'amount' => $npsAmount
                    ]);
                } else {
                    Log::info("❌ NPS not calculated (ESI is false, but NPS is false)");
                }
            } else {
                Log::info("❌ NPS not calculated (ESI is true)");
            }

            $statutoryDeductions[] = [
                'componentName' => 'Employee NPS',
                'payType' => 'Statutory Deduction',
                'paymentNature' => 'Monthly',
                'formula' => $npsFormula,
                'percentage' => round($npsPercentage, 2),
                'calculatedValue' => round($npsAmount, 0),
                'annualCalculatedValue' => round($npsAmount * 12, 0),
                'isStatutory' => true
            ];

            // ✅ 3. ESI Calculation
            $esiAmount = 0;
            $esiFormula = 'Not Applicable';
            $esiPercentage = 0;

            if ($empStateInsurance == 1) {
                Log::info("🔍 ESI Calculation Started:", [
                    'corpId' => $corpId,
                    'companyName' => $companyName,
                    'ctc' => $ctc,
                    'basicSalary' => $basicSalary
                ]);
                
                $esiData = null;
                $esiCorpIdVariations = ['corpId', 'corp_id', 'CorpId', 'CORP_ID'];
                $esiCompanyVariations = ['companyName', 'company_name', 'CompanyName', 'COMPANY_NAME'];
                
                // Simplified query - use direct column names from migration
                try {
                    // First try exact match
                    $esiData = DB::table('esi')
                        ->where('corpId', $corpId)
                        ->where('companyName', $companyName)
                        ->where('incomeRange', '>=', $ctc)
                        ->orderBy('incomeRange', 'asc')
                        ->first();
                    
                    // If not found, try with LIKE (handles trailing spaces/dots)
                    if (!$esiData) {
                        $esiData = DB::table('esi')
                            ->where('corpId', $corpId)
                            ->where('companyName', 'LIKE', trim($companyName) . '%')
                            ->where('incomeRange', '>=', $ctc)
                            ->orderBy('incomeRange', 'asc')
                            ->first();
                    }
                    
                    Log::info("ESI Query Result:", [
                        'corpId' => $corpId,
                        'companyName' => $companyName,
                        'ctc' => $ctc,
                        'incomeRangeCondition' => 'incomeRange >= ' . $ctc,
                        'found' => $esiData ? 'YES' : 'NO',
                        'data' => $esiData
                    ]);
                } catch (\Exception $e) {
                    Log::error("ESI Query Error:", [
                        'error' => $e->getMessage()
                    ]);
                }

                if ($esiData && isset($esiData->esiAmount) && $esiData->esiAmount > 0) {
                    $esiPercentage = round((float)$esiData->esiAmount, 2);
                    $esiAmount = round(($basicSalary * $esiPercentage) / 100, 0);
                    $esiFormula = "{$esiPercentage}% of Basic";
                    
                    Log::info("✅ ESI Calculated:", [
                        'esiData' => $esiData,
                        'percentage' => $esiPercentage,
                        'amount' => $esiAmount
                    ]);
                } else {
                    Log::warning("❌ ESI NOT Calculated:", [
                        'esiDataFound' => $esiData ? 'YES' : 'NO',
                        'esiAmount' => $esiData->esiAmount ?? 'N/A',
                        'reason' => !$esiData ? 'No ESI data found in table' : 'ESI amount is 0 or invalid'
                    ]);
                }
            }

            $statutoryDeductions[] = [
                'componentName' => 'Employee ESI',
                'payType' => 'Statutory Deduction',
                'paymentNature' => 'Monthly',
                'formula' => $esiFormula,
                'percentage' => round($esiPercentage, 2),
                'calculatedValue' => round($esiAmount, 0),
                'annualCalculatedValue' => round($esiAmount * 12, 0),
                'isStatutory' => true
            ];

            Log::info("=== FINAL STATUTORY DEDUCTIONS ===", [
                'vpf' => $vpfAmount,
                'nps' => $npsAmount,
                'esi' => $esiAmount,
                'total_deductions' => $vpfAmount + $npsAmount + $esiAmount,
                'note' => 'Medical never appears in deductions - only in gross if applicable'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in calculateStatutoryDeductions:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return $this->getDefaultStatutoryDeductions();
        }

        return $statutoryDeductions;
    }

    /**
     * ✅ **FIXED:** Calculate medical amount for gross - ONLY when NPS=false AND ESI=false
     */
    private function calculateMedicalForGross($corpId, $empCode, $basicSalary)
    {
        try {
            // Get employee statutory details
            $employeeStatutory = null;
            $corpIdVariations = ['corpId', 'corp_id', 'CorpId', 'CORP_ID'];
            $empCodeVariations = ['empCode', 'emp_code', 'EmpCode', 'EMP_CODE'];
            
            foreach ($corpIdVariations as $corpCol) {
                foreach ($empCodeVariations as $empCol) {
                    try {
                        $test = DB::table('employee_statutory_details')
                            ->whereRaw("$corpCol = ?", [$corpId])
                            ->whereRaw("$empCol = ?", [$empCode])
                            ->first();
                        
                        if ($test) {
                            $employeeStatutory = $test;
                            break 2;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            if (!$employeeStatutory) {
                return ['amount' => 0, 'formula' => 'Not Applicable', 'applicable' => false];
            }

            // ✅ FIXED: Check both EmployerCtbToNPSYN and EmployerCtbnToNPSYN (lowercase 'n')
            $employerCtbToNPS = $employeeStatutory->EmployerCtbnToNPSYN ?? 
                               $employeeStatutory->EmployerCtbToNPSYN ?? 
                               $employeeStatutory->employer_ctb_to_nps_yn ?? 
                               $employeeStatutory->employer_ctbn_to_nps_yn ?? 0;
            $empStateInsurance = $employeeStatutory->EmpStateInsuranceYN ?? $employeeStatutory->emp_state_insurance_yn ?? 0;
            $employerPercentage = $employeeStatutory->EmployerPercentage ?? $employeeStatutory->employer_percentage ?? 0;

            Log::info("Medical for Gross Check:", [
                'employerCtbToNPS' => $employerCtbToNPS,
                'empStateInsurance' => $empStateInsurance,
                'employerPercentage' => $employerPercentage,
                'condition' => 'Medical applies ONLY if NPS=0 AND ESI=0 AND percentage>0'
            ]);

            // ✅ Medical goes to GROSS ONLY when: NPS=false AND ESI=false AND percentage>0
            if ($employerCtbToNPS == 0 && $empStateInsurance == 0 && $employerPercentage > 0) {
                $employerPercentage = round((float)$employerPercentage, 2);
                $medicalAmount = round(($basicSalary * $employerPercentage) / 100, 0);
                $formula = "{$employerPercentage}% of Basic";
                
                Log::info("✅ Medical ADDED to GROSS:", [
                    'amount' => $medicalAmount,
                    'percentage' => $employerPercentage,
                    'reason' => 'NPS=false AND ESI=false'
                ]);
                
                return ['amount' => round($medicalAmount, 0), 'formula' => $formula, 'applicable' => true];
            } else {
                Log::info("❌ Medical NOT added to GROSS:", [
                    'reason' => "NPS={$employerCtbToNPS}, ESI={$empStateInsurance}, Percentage={$employerPercentage}"
                ]);
            }

            return ['amount' => 0, 'formula' => 'Not Applicable', 'applicable' => false];
            
        } catch (\Exception $e) {
            Log::error('Error calculating medical for gross:', ['error' => $e->getMessage()]);
            return ['amount' => 0, 'formula' => 'Not Applicable', 'applicable' => false];
        }
    }

    /**
     * ✅ **FIXED:** Get default statutory deductions - NO MEDICAL in deductions
     */
    private function getDefaultStatutoryDeductions()
    {
        return [
            [
                'componentName' => 'Employee VPF',
                'payType' => 'Statutory Deduction',
                'paymentNature' => 'Monthly',
                'formula' => 'Not Applicable',
                'percentage' => 0,
                'calculatedValue' => 0,
                'annualCalculatedValue' => 0,
                'isStatutory' => true
            ],
            [
                'componentName' => 'Employee NPS',
                'payType' => 'Statutory Deduction',
                'paymentNature' => 'Monthly',
                'formula' => 'Not Applicable',
                'percentage' => 0,
                'calculatedValue' => 0,
                'annualCalculatedValue' => 0,
                'isStatutory' => true
            ],
            [
                'componentName' => 'Employee ESI',
                'payType' => 'Statutory Deduction',
                'paymentNature' => 'Monthly',
                'formula' => 'Not Applicable',
                'percentage' => 0,
                'calculatedValue' => 0,
                'annualCalculatedValue' => 0,
                'isStatutory' => true
            ]
        ];
    }

    /**
     * ✅ **UPDATED:** Main method with clean logic - NO DUPLICATES
     */
    public function fetchCompletePayrollBreakdownWithStatutory($groupName, $corpId, $basicSalary, $ctc, $empCode, $companyName)
    {
        // Validation (same as before)
        if (!is_numeric($basicSalary) || $basicSalary < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Valid basic salary is required.',
                'data' => []
            ], 400);
        }

        if (!is_numeric($ctc) || $ctc < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Valid CTC is required.',
                'data' => []
            ], 400);
        }

        try {
            // Get existing payroll components
            $grossResponse = $this->fetchGrossByGroupName($groupName, $basicSalary);
            $deductionsResponse = $this->fetchDeductionsByGroupName($groupName, $basicSalary);
            $benefitsResponse = $this->fetchOtherBenefitsAllowances($groupName, $corpId, $basicSalary);

            // Extract data from responses
            $grossData = $grossResponse->getData(true)['data'] ?? [];
            $deductionsData = $deductionsResponse->getData(true)['data'] ?? [];
            $benefitsData = $benefitsResponse->getData(true)['data'] ?? [];

            // Get existing components
            $grossComponents = $grossData['components'] ?? [];
            $deductionComponents = $deductionsData['deductions'] ?? [];
            $benefitComponents = $benefitsData['otherBenefitsAllowances'] ?? [];

            // ✅ Calculate statutory deductions (VPF, NPS, ESI only)
            $statutoryDeductions = $this->calculateStatutoryDeductions($corpId, $empCode, $companyName, $basicSalary, $ctc);

            // ✅ Check if Medical should be added to GROSS (separate check)
            $medicalForGross = $this->calculateMedicalForGross($corpId, $empCode, $basicSalary);

            // ✅ Add Medical to gross components ONLY if applicable
            if ($medicalForGross['amount'] > 0) {
                $grossComponents[] = [
                    'componentName' => 'Medical',
                    'payType' => 'Addition',
                    'paymentNature' => 'Monthly',
                    'formula' => $medicalForGross['formula'],
                    'calculatedValue' => $medicalForGross['amount'],
                    'annualCalculatedValue' => $medicalForGross['amount'] * 12,
                    'isStatutory' => true
                ];
            }

            // ✅ Merge statutory deductions with existing deductions (NO MEDICAL here)
            $allDeductions = array_merge($deductionComponents, $statutoryDeductions);

            // Recalculate totals
            $existingGrossMonthly = $grossData['totalGross']['monthly'] ?? 0;
            $existingGrossAnnual = $grossData['totalGross']['annual'] ?? 0;
            
            $grossMonthly = $existingGrossMonthly + $medicalForGross['amount'];
            $grossAnnual = $existingGrossAnnual + ($medicalForGross['amount'] * 12);
            
            $existingDeductionsMonthly = $deductionsData['totalDeductions']['monthly'] ?? 0;
            $existingDeductionsAnnual = $deductionsData['totalDeductions']['annual'] ?? 0;
            
            $statutoryDeductionsMonthly = array_sum(array_column($statutoryDeductions, 'calculatedValue'));
            $statutoryDeductionsAnnual = array_sum(array_column($statutoryDeductions, 'annualCalculatedValue'));
            
            $totalDeductionsMonthly = $existingDeductionsMonthly + $statutoryDeductionsMonthly;
            $totalDeductionsAnnual = $existingDeductionsAnnual + $statutoryDeductionsAnnual;

            $benefitsMonthly = $benefitsData['totalBenefits']['monthly'] ?? 0;
            $benefitsAnnual = $benefitsData['totalBenefits']['annual'] ?? 0;

            $netSalaryMonthly = $grossMonthly - $totalDeductionsMonthly;
            $netSalaryAnnual = $grossAnnual - $totalDeductionsAnnual;

            return response()->json([
                'status' => true,
                'data' => [
                    'groupName' => $groupName,
                    'corpId' => $corpId,
                    'empCode' => $empCode,
                    'companyName' => $companyName,
                    'basicSalary' => (float)$basicSalary,
                    'ctc' => (float)$ctc,
                    'gross' => $grossComponents, // ✅ Includes Medical only if NPS=false AND ESI=false
                    'deductions' => $allDeductions, // ✅ NO Medical ever in deductions
                    'otherBenefitsAllowances' => $benefitComponents,
                    'statutory_calculations' => [
                        'vpf' => $this->findComponentByName($statutoryDeductions, 'Employee VPF'),
                        'nps' => $this->findComponentByName($statutoryDeductions, 'Employee NPS'),
                        'esi' => $this->findComponentByName($statutoryDeductions, 'Employee ESI'),
                        'medical_in_gross' => $medicalForGross // ✅ Medical info for gross only
                    ],
                    'summary' => [
                        'totalGross' => [
                            'monthly' => $grossMonthly, // ✅ Includes medical if applicable
                            'annual' => $grossAnnual
                        ],
                        'totalDeductions' => [
                            'monthly' => $totalDeductionsMonthly,
                            'annual' => $totalDeductionsAnnual,
                            'breakdown' => [
                                'existing' => ['monthly' => $existingDeductionsMonthly, 'annual' => $existingDeductionsAnnual],
                                'statutory' => ['monthly' => $statutoryDeductionsMonthly, 'annual' => $statutoryDeductionsAnnual]
                            ]
                        ],
                        'totalBenefits' => [
                            'monthly' => $benefitsMonthly,
                            'annual' => $benefitsAnnual
                        ],
                        'netSalary' => [
                            'monthly' => $netSalaryMonthly,
                            'annual' => $netSalaryAnnual
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while calculating payroll breakdown.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to find a component by name in an array
     */
    private function findComponentByName($components, $componentName)
    {
        foreach ($components as $component) {
            if ($component['componentName'] === $componentName) {
                return $component;
            }
        }
        return null;
    }

    /**
     * ✅ **NEW METHOD:** Fetch complete payroll breakdown with improved statutory logic - NO DUPLICATES
     * This is the corrected version that properly filters out statutory components from paygroup
     */
    public function fetchCompletePayrollBreakdownImproved($groupName, $corpId, $basicSalary, $ctc, $empCode, $companyName)
    {
        // Validation
        if (!is_numeric($basicSalary) || $basicSalary < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Valid basic salary is required.',
                'data' => []
            ], 400);
        }

        if (!is_numeric($ctc) || $ctc < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Valid CTC is required.',
                'data' => []
            ], 400);
        }

        try {
            // ✅ Define statutory component names that should NOT come from paygroup_configurations
            $statutoryComponentNames = [
                'Employee VPF', 
                'Employee ESI', 
                'Employee NPS', 
                'Medical'
            ];

            // Get existing payroll components
            $grossResponse = $this->fetchGrossByGroupName($groupName, $basicSalary);
            $deductionsResponse = $this->fetchDeductionsByGroupName($groupName, $basicSalary);
            $benefitsResponse = $this->fetchOtherBenefitsAllowances($groupName, $corpId, $basicSalary);

            // Extract data from responses
            $grossData = $grossResponse->getData(true)['data'] ?? [];
            $deductionsData = $deductionsResponse->getData(true)['data'] ?? [];
            $benefitsData = $benefitsResponse->getData(true)['data'] ?? [];

            // Get components and FILTER OUT any statutory components from paygroup
            $grossComponents = array_values(array_filter(
                $grossData['components'] ?? [],
                function($component) use ($statutoryComponentNames) {
                    return !in_array($component['componentName'], $statutoryComponentNames);
                }
            ));

            $deductionComponents = array_values(array_filter(
                $deductionsData['deductions'] ?? [],
                function($component) use ($statutoryComponentNames) {
                    return !in_array($component['componentName'], $statutoryComponentNames);
                }
            ));

            $benefitComponents = array_values(array_filter(
                $benefitsData['otherBenefitsAllowances'] ?? [],
                function($component) use ($statutoryComponentNames) {
                    return !in_array($component['componentName'], $statutoryComponentNames);
                }
            ));

            // ✅ NOW calculate statutory components separately
            $statutoryDeductions = $this->calculateStatutoryDeductions($corpId, $empCode, $companyName, $basicSalary, $ctc);
            $medicalForGross = $this->calculateMedicalForGross($corpId, $empCode, $basicSalary);

            // ✅ Add Medical to gross ONLY if applicable
            if ($medicalForGross['amount'] > 0) {
                $grossComponents[] = [
                    'componentName' => 'Medical',
                    'payType' => 'Addition',
                    'paymentNature' => 'Monthly',
                    'formula' => $medicalForGross['formula'],
                    'calculatedValue' => round($medicalForGross['amount'], 0),
                    'annualCalculatedValue' => round($medicalForGross['amount'] * 12, 0),
                    'isStatutory' => true
                ];
            }

            // ✅ NEW: Add Employer NPS to benefits with same amount as Employee NPS
            $employeeNPS = $this->findComponentByName($statutoryDeductions, 'Employee NPS');
            if ($employeeNPS && $employeeNPS['calculatedValue'] > 0) {
                // Find and update Employer NPS in benefits if it exists
                $employerNPSFound = false;
                foreach ($benefitComponents as &$benefit) {
                    if ($benefit['componentName'] === 'Employer NPS') {
                        $benefit['calculatedValue'] = round($employeeNPS['calculatedValue'], 0);
                        $benefit['annualCalculatedValue'] = round($employeeNPS['annualCalculatedValue'], 0);
                        $benefit['formula'] = $employeeNPS['formula'];
                        $benefit['isStatutory'] = true;
                        $employerNPSFound = true;
                        break;
                    }
                }
                
                // If Employer NPS not found in benefits, add it
                if (!$employerNPSFound) {
                    $benefitComponents[] = [
                        'componentName' => 'Employer NPS',
                        'payType' => 'Benefits',
                        'paymentNature' => 'Monthly',
                        'formula' => $employeeNPS['formula'],
                        'calculatedValue' => round($employeeNPS['calculatedValue'], 0),
                        'annualCalculatedValue' => round($employeeNPS['annualCalculatedValue'], 0),
                        'isStatutory' => true
                    ];
                }
            }

            // ✅ Merge statutory deductions (NO Medical here)
            $allDeductions = array_merge($deductionComponents, $statutoryDeductions);

            // ✅ Ensure all components have properly rounded values (whole numbers)
            foreach ($grossComponents as &$component) {
                $component['calculatedValue'] = round($component['calculatedValue'], 0);
                $component['annualCalculatedValue'] = round($component['annualCalculatedValue'], 0);
            }
            
            foreach ($allDeductions as &$component) {
                $component['calculatedValue'] = round($component['calculatedValue'], 0);
                $component['annualCalculatedValue'] = round($component['annualCalculatedValue'], 0);
            }
            
            foreach ($benefitComponents as &$component) {
                $component['calculatedValue'] = round($component['calculatedValue'], 0);
                $component['annualCalculatedValue'] = round($component['annualCalculatedValue'], 0);
            }

            // Recalculate totals from filtered components (all rounded to whole numbers)
            $grossMonthly = round(array_sum(array_column($grossComponents, 'calculatedValue')), 0);
            $grossAnnual = round(array_sum(array_column($grossComponents, 'annualCalculatedValue')), 0);
            
            $totalDeductionsMonthly = round(array_sum(array_column($allDeductions, 'calculatedValue')), 0);
            $totalDeductionsAnnual = round(array_sum(array_column($allDeductions, 'annualCalculatedValue')), 0);

            $benefitsMonthly = round(array_sum(array_column($benefitComponents, 'calculatedValue')), 0);
            $benefitsAnnual = round(array_sum(array_column($benefitComponents, 'annualCalculatedValue')), 0);

            $netSalaryMonthly = round($grossMonthly - $totalDeductionsMonthly, 0);
            $netSalaryAnnual = round($grossAnnual - $totalDeductionsAnnual, 0);
            $totalCTCMonthly = round($grossMonthly + $benefitsMonthly, 0);
            $totalCTCAnnual = round($grossAnnual + $benefitsAnnual, 0);

            return response()->json([
                'status' => true,
                'message' => 'Payroll breakdown calculated successfully with statutory components',
                'data' => [
                    'groupName' => $groupName,
                    'corpId' => $corpId,
                    'empCode' => $empCode,
                    'companyName' => $companyName,
                    'basicSalary' => round((float)$basicSalary, 0),
                    'ctc' => round((float)$ctc, 0),
                    'gross' => $grossComponents, // ✅ NO duplicates - filtered and clean
                    'deductions' => $allDeductions, // ✅ NO duplicates - filtered and clean
                    'otherBenefitsAllowances' => $benefitComponents,
                    'statutory_calculations' => [
                        'vpf' => $this->findComponentByName($statutoryDeductions, 'Employee VPF'),
                        'nps' => $this->findComponentByName($statutoryDeductions, 'Employee NPS'),
                        'esi' => $this->findComponentByName($statutoryDeductions, 'Employee ESI'),
                        'medical_in_gross' => $medicalForGross['applicable'] ? $medicalForGross : null
                    ],
                    'summary' => [
                        'totalGross' => [
                            'monthly' => round($grossMonthly, 0),
                            'annual' => round($grossAnnual, 0)
                        ],
                        'totalDeductions' => [
                            'monthly' => round($totalDeductionsMonthly, 0),
                            'annual' => round($totalDeductionsAnnual, 0)
                        ],
                        'totalBenefits' => [
                            'monthly' => round($benefitsMonthly, 0),
                            'annual' => round($benefitsAnnual, 0)
                        ],
                        'netSalary' => [
                            'monthly' => round($netSalaryMonthly, 0),
                            'annual' => round($netSalaryAnnual, 0)
                        ],
                        'totalCTC' => [
                            'monthly' => round($totalCTCMonthly, 0),
                            'annual' => round($totalCTCAnnual, 0)
                        ]
                    ],
                    'component_counts' => [
                        'gross_components' => count($grossComponents),
                        'deduction_components' => count($allDeductions),
                        'benefit_components' => count($benefitComponents),
                        'total_components' => count($grossComponents) + count($allDeductions) + count($benefitComponents)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in fetchCompletePayrollBreakdownImproved:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while calculating payroll breakdown.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
