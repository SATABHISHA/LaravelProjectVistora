<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaygroupConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        if (!is_numeric($basicSalary) || $basicSalary <= 0) {
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
        $totalGross = 0;

        foreach ($includedComponents as $componentName) {
            // Fetch from pay_components with conditions
            $payComponent = DB::table('pay_components')
                ->where('componentName', $componentName)
                ->where('isPartOfCtcYn', 1)
                ->where('payType', 'Addition')
                ->first();

            if ($payComponent) {
                // Try different approaches to fetch formula
                $formulaBuilder = null;
                $formula = null;
                
                // Approach 1: Match both componentGroupName and componentName with componentName
                $formulaBuilder = DB::table('formula_builders')
                    ->where('componentGroupName', $componentName)
                    ->where('componentName', $componentName)
                    ->first();
                
                // Approach 2: If not found, try matching only componentName
                if (!$formulaBuilder) {
                    $formulaBuilder = DB::table('formula_builders')
                        ->where('componentName', $componentName)
                        ->first();
                }
                
                // Approach 3: If still not found, try matching componentGroupName only
                if (!$formulaBuilder) {
                    $formulaBuilder = DB::table('formula_builders')
                        ->where('componentGroupName', $componentName)
                        ->first();
                }

                $calculatedValue = 0;

                if ($formulaBuilder && !empty($formulaBuilder->formula)) {
                    $formula = $formulaBuilder->formula;
                    // Calculate the formula dynamically with basic salary
                    $calculatedValue = $this->calculateFormula($formula, $basicSalary);
                }

                $componentResult = [
                    'componentName' => $componentName,
                    'paymentNature' => $payComponent->paymentNature,
                    'formula' => $formula,
                    'calculatedValue' => $calculatedValue,
                    // Add debug info temporarily
                   /* 'debug' => [
                        'formula_found' => $formulaBuilder ? true : false,
                        'formula_raw' => $formulaBuilder->formula ?? null,
                        'payComponent_found' => $payComponent ? true : false
                    ]*/
                ];

                $result[] = $componentResult;
                $totalGross += $calculatedValue;
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'groupName' => $groupName,
                'basicSalary' => (float)$basicSalary,
                'components' => $result,
                'totalGross' => $totalGross
            ]
        ]);
    }

    // Calculate dynamic formula with basic salary
    private function calculateFormula($formula, $basicSalary)
    {
        try {
            // Basic security check - only allow numbers, operators, and common functions
            if (!preg_match('/^[0-9+\-*\/().\s_A-Za-z]+$/', $formula)) {
                throw new \Exception('Invalid formula format');
            }

            // Replace formula variables with actual values
            $processedFormula = $this->processFormulaVariables($formula, $basicSalary);

            // Evaluate the formula safely
            $result = eval("return $processedFormula;");
            
            return is_numeric($result) ? (float)$result : 0;
        } catch (\Exception $e) {
            // Log error and return 0 for safety
            \Log::warning("Formula calculation error: " . $e->getMessage() . " for formula: " . $formula);
            return 0;
        }
    }

    // Process formula variables with basic salary
    private function processFormulaVariables($formula, $basicSalary)
    {
        // Replace common variables with actual values
        $variables = [
            'BASIC_SALARY' => $basicSalary,
            'BasicSalary' => $basicSalary,
            'Basic_Salary' => $basicSalary,
            'basic_salary' => $basicSalary,
            'BASIC' => $basicSalary,
            'Basic' => $basicSalary,
            'basic' => $basicSalary,
            // Add more variable patterns as needed based on your formulas
            'DA' => $basicSalary * 0.1,  // Example: DA = 10% of basic
            'HRA' => $basicSalary * 0.4, // Example: HRA = 40% of basic
        ];

        foreach ($variables as $key => $value) {
            // Safely replace variables in the formula
            $formula = preg_replace('/\b' . preg_quote($key, '/') . '\b/', $value, $formula);
        }

        return $formula;
    }

}
