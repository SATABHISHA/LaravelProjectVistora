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
        if (!is_numeric($basicSalary) || $basicSalary < 0) { // Allow 0 basic salary
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
                $calculatedValue = 0.0;
                $formula = null;

                // Check if component name is "Basic" (case-insensitive)
                if (strtolower(trim($componentName)) === 'basic') {
                    $calculatedValue = (float)$basicSalary;
                    $formula = 'Basic'; // A fixed identifier for Basic salary
                } else {
                    // ** NEW LOGIC **
                    // Use the new helper method for all other components
                    $calculationResult = $this->calculateComponentValue($componentName, $basicSalary);
                    $calculatedValue = $calculationResult['calculatedValue'];
                    $formula = $calculationResult['formula'];
                }

                $componentResult = [
                    'componentName' => $componentName,
                    'paymentNature' => $payComponent->paymentNature,
                    'formula' => $formula,
                    'calculatedValue' => $calculatedValue
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

    /**
     * NEW HELPER METHOD for calculating component value based on the new formula_builders logic.
     */
    private function calculateComponentValue($componentName, $basicSalary)
    {
        $formulaBuilder = DB::table('formula_builders')
            ->where('componentName', $componentName)
            ->first();

        // If no formula is defined for the component, return defaults.
        if (!$formulaBuilder) {
            return ['calculatedValue' => 0.0, 'formula' => null];
        }

        $formulaType = $formulaBuilder->formula ?? null;
        $calculatedValue = 0.0;

        // Only calculate if the formula type is 'Percent'.
        if ($formulaType === 'Percent') {
            $refersTo = $formulaBuilder->componentNameRefersTo ?? null;
            $percentage = (float)($formulaBuilder->referenceValue ?? 0);

            // For now, we only handle calculations based on 'Basic' salary.
            // This can be expanded later if formulas can refer to other components.
            if (strtolower($refersTo) === 'basic' && $percentage > 0) {
                $calculatedValue = ($percentage / 100) * (float)$basicSalary;
            }
        }
        // For 'Fixed' or 'Variable', the calculated value is 0.0 as per requirements.

        return ['calculatedValue' => round($calculatedValue, 2), 'formula' => $formulaType];
    }


    // Fetch OtherBenefitsAllowances by GroupName and corpId
    public function fetchOtherBenefitsAllowances($groupName, $corpId)
    {
        // Get CTCAllowances from paygroup_configurations
        $paygroup = DB::table('paygroup_configurations')
            ->where('GroupName', $groupName)
            ->where('corpId', $corpId)
            ->first();

        if (!$paygroup || empty($paygroup->CTCAllowances)) {
            return response()->json([
                'status' => false,
                'message' => 'GroupName not found for this corpId or no CTCAllowances available.',
                'data' => []
            ], 404);
        }

        // Split CTCAllowances by comma and trim each value
        $ctcAllowances = array_filter(array_map('trim', explode(',', $paygroup->CTCAllowances)));

        $result = [];

        foreach ($ctcAllowances as $allowanceName) {
            // Fetch from pay_components for each allowance
            $payComponent = DB::table('pay_components')
                ->where('componentName', $allowanceName)
                ->first();

            if ($payComponent) {
                $allowanceResult = [
                    'componentName' => $payComponent->componentName,
                    'payType' => $payComponent->payType ?? null,
                    'paymentNature' => $payComponent->paymentNature ?? null,
                    'isPartOfCtcYn' => $payComponent->isPartOfCtcYn ?? null,
                    'componentDescription' => $payComponent->componentDescription ?? null,
                    'calculatedValue' => 0.0
                ];

                $result[] = $allowanceResult;
            } else {
                // Even if pay component not found, add the allowance with default values
                $allowanceResult = [
                    'componentName' => $allowanceName,
                    'payType' => null,
                    'paymentNature' => null,
                    'isPartOfCtcYn' => null,
                    'componentDescription' => null,
                    'calculatedValue' => 0.0
                ];

                $result[] = $allowanceResult;
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'groupName' => $groupName,
                'corpId' => $corpId,
                'otherBenefitsAllowances' => $result,  // Remove ctcAllowances, only return processed result
                'totalCount' => count($result)
            ]
        ]);
    }

    // Fetch Deductions with calculated values
    public function fetchDeductionsByGroupName($groupName, $basicSalary)
    {
        // Validate basic salary
        if (!is_numeric($basicSalary) || $basicSalary < 0) { // Allow 0 basic salary
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
        $totalDeductions = 0;

        foreach ($includedComponents as $componentName) {
            // Fetch from pay_components with conditions for Deductions
            $payComponent = DB::table('pay_components')
                ->where('componentName', $componentName)
                ->where('isPartOfCtcYn', 1)
                ->where('payType', 'Deduction')
                ->first();

            if ($payComponent) {
                // ** NEW LOGIC **
                // Use the new helper method for all deduction components
                $calculationResult = $this->calculateComponentValue($componentName, $basicSalary);
                $calculatedValue = $calculationResult['calculatedValue'];
                $formula = $calculationResult['formula'];

                $componentResult = [
                    'componentName' => $componentName,
                    'paymentNature' => $payComponent->paymentNature,
                    'formula' => $formula,
                    'calculatedValue' => $calculatedValue
                ];

                $result[] = $componentResult;
                $totalDeductions += $calculatedValue;
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'groupName' => $groupName,
                'basicSalary' => (float)$basicSalary,
                'deductions' => $result,
                'totalDeductions' => $totalDeductions
            ]
        ]);
    }

    // Add the Save Payroll Data method(not required in this way)
    public function savePayrollData(Request $request)
    {
        $data = $request->all();
        
        // Validate the JSON structure
        $validator = Validator::make($data, [
            'Gross' => 'required|array',
            'Allowances' => 'required|array',
            'Deductions' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid data structure',
                'errors' => $validator->errors()
            ], 400);
        }
        
        return response()->json([
            'status' => true,
            'message' => 'Payroll data saved successfully',
            'data' => [
                'grossCount' => count($data['Gross']),
                'allowancesCount' => count($data['Allowances']),
                'deductionsCount' => count($data['Deductions']),
                'totalGross' => array_sum(array_column($data['Gross'], 'calculatedValue')),
                'totalAllowances' => array_sum(array_column($data['Allowances'], 'calculatedValue')),
                'totalDeductions' => array_sum(array_column($data['Deductions'], 'calculatedValue')),
                'savedAt' => now()->toDateTimeString()
            ]
        ]);
    }

}
