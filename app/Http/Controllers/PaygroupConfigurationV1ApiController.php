<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaygroupConfigurationV1;
use App\Models\FormulaBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaygroupConfigurationV1ApiController extends Controller
{
    // Add or Update PaygroupConfigurationV1 by puid
    public function storeOrUpdate(Request $request)
    {
        try {
            $request->validate([
                'corpId' => 'required|string',
                'GroupName' => 'required|string',
            ]);

            $data = $request->all();
            $puid = $data['puid'] ?? null;

            if ($puid) {
                // Update existing record
                $paygroup = PaygroupConfigurationV1::where('puid', $puid)->first();

                if (!$paygroup) {
                    return response()->json([
                        'status' => false,
                        'message' => 'PaygroupConfigurationV1 not found for the given puid.'
                    ], 404);
                }

                // Check for duplicate GroupName for the same corpId (excluding current record)
                $duplicate = PaygroupConfigurationV1::where('corpId', $data['corpId'])
                    ->where('GroupName', $data['GroupName'])
                    ->where('puid', '!=', $puid)
                    ->exists();

                if ($duplicate) {
                    return response()->json([
                        'status' => false,
                        'message' => 'GroupName already exists for this corpId.'
                    ], 409);
                }

                $paygroup->update($data);

                return response()->json([
                    'status' => true,
                    'message' => 'PaygroupConfigurationV1 updated successfully',
                    'data' => $paygroup
                ]);
            } else {
                // Create new record (puid auto-generated)
                $duplicate = PaygroupConfigurationV1::where('corpId', $data['corpId'])
                    ->where('GroupName', $data['GroupName'])
                    ->exists();

                if ($duplicate) {
                    return response()->json([
                        'status' => false,
                        'message' => 'GroupName already exists for this corpId.'
                    ], 409);
                }

                unset($data['puid']); // Ensure puid is auto-generated
                $paygroup = PaygroupConfigurationV1::create($data);

                return response()->json([
                    'status' => true,
                    'message' => 'PaygroupConfigurationV1 created successfully',
                    'data' => $paygroup
                ], 201);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // Fetch all PaygroupConfigurationV1 by corpId
    public function getByCorpId($corpId)
    {
        $data = PaygroupConfigurationV1::where('corpId', $corpId)->get();
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // Delete PaygroupConfigurationV1 by puid (also deletes related formula_builders)
    public function destroy($puid)
    {
        $paygroup = PaygroupConfigurationV1::where('puid', $puid)->first();

        if (!$paygroup) {
            return response()->json([
                'status' => false,
                'message' => 'PaygroupConfigurationV1 not found'
            ], 404);
        }

        // Delete related formula_builders where paygroupPuid matches this puid
        $deletedFormulaBuilders = FormulaBuilder::where('paygroupPuid', $puid)->delete();

        $paygroup->delete();

        return response()->json([
            'status' => true,
            'message' => 'PaygroupConfigurationV1 and related formula builders deleted successfully',
            'deletedFormulaBuilders' => $deletedFormulaBuilders
        ]);
    }

    // Get distinct GroupNames by corpId
    public function getGroupNamesByCorpId($corpId)
    {
        $groupNames = PaygroupConfigurationV1::where('corpId', $corpId)
            ->select('puid', 'GroupName', 'IncludedComponents', 'created_at', 'updated_at')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $groupNames,
            'totalCount' => $groupNames->count()
        ]);
    }

    // Complete improved payroll breakdown using paygroup_configuration_v1s and formula_builders
    public function fetchCompletePayrollBreakdownImproved($groupName, $corpId, $ctc)
    {
        if (!is_numeric($ctc) || $ctc < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Valid CTC is required.',
                'data' => []
            ], 400);
        }

        $ctc = (float) $ctc;

        try {
            // Get paygroup config from paygroup_configuration_v1s
            $paygroup = PaygroupConfigurationV1::where('GroupName', $groupName)
                ->where('corpId', $corpId)
                ->first();

            if (!$paygroup) {
                return response()->json([
                    'status' => false,
                    'message' => 'GroupName not found for this corpId in paygroup_configuration_v1s.',
                    'data' => []
                ], 404);
            }

            if (empty($paygroup->IncludedComponents)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No IncludedComponents found for this paygroup.',
                    'data' => []
                ], 404);
            }

            $paygroupPuid = $paygroup->puid;
            $includedComponents = array_filter(array_map('trim', explode(',', $paygroup->IncludedComponents)));

            // Get all formula_builders for this paygroup puid
            $formulaBuilders = FormulaBuilder::where('paygroupPuid', $paygroupPuid)->get()->keyBy('componentName');

            // First pass: calculate Basic from CTC (or use formula_builder definition)
            $basicSalary = $this->calculateBasicFromCtc($formulaBuilders, $ctc);

            // Categorize components
            $grossComponents = [];
            $deductionComponents = [];
            $benefitComponents = [];

            foreach ($includedComponents as $componentName) {
                $payComponent = DB::table('pay_components')
                    ->where('componentName', $componentName)
                    ->where('isPartOfCtcYn', 1)
                    ->first();

                if (!$payComponent) {
                    continue;
                }

                $calculatedValue = 0.0;
                $formula = 'N/A';

                if (strtolower(trim($componentName)) === 'basic') {
                    $calculatedValue = round($basicSalary, 0);
                    $formula = 'Basic';
                } else {
                    $result = $this->calculateComponentValueV1($componentName, $basicSalary, $formulaBuilders);
                    $calculatedValue = round($result['calculatedValue'], 0);
                    $formula = $result['formula'];
                }

                $annualCalculatedValue = round($calculatedValue * 12, 0);

                $componentResult = [
                    'componentName' => $componentName,
                    'payType' => $payComponent->payType,
                    'paymentNature' => $payComponent->paymentNature,
                    'formula' => $formula,
                    'calculatedValue' => round($calculatedValue, 0),
                    'annualCalculatedValue' => round($annualCalculatedValue, 0)
                ];

                $payType = $payComponent->payType;
                if ($payType === 'Addition' || $payType === 'Addition & Deduction') {
                    $grossComponents[] = $componentResult;
                }
                if ($payType === 'Deduction' || $payType === 'Addition & Deduction') {
                    $deductionComponents[] = $componentResult;
                }
                if ($payType === 'Benefits') {
                    $benefitComponents[] = $componentResult;
                }
            }

            // Round all values
            foreach ($grossComponents as &$c) {
                $c['calculatedValue'] = round($c['calculatedValue'], 0);
                $c['annualCalculatedValue'] = round($c['annualCalculatedValue'], 0);
            }
            foreach ($deductionComponents as &$c) {
                $c['calculatedValue'] = round($c['calculatedValue'], 0);
                $c['annualCalculatedValue'] = round($c['annualCalculatedValue'], 0);
            }
            foreach ($benefitComponents as &$c) {
                $c['calculatedValue'] = round($c['calculatedValue'], 0);
                $c['annualCalculatedValue'] = round($c['annualCalculatedValue'], 0);
            }

            // Calculate totals
            $grossMonthly = round(array_sum(array_column($grossComponents, 'calculatedValue')), 0);
            $grossAnnual = round(array_sum(array_column($grossComponents, 'annualCalculatedValue')), 0);

            $totalDeductionsMonthly = round(array_sum(array_column($deductionComponents, 'calculatedValue')), 0);
            $totalDeductionsAnnual = round(array_sum(array_column($deductionComponents, 'annualCalculatedValue')), 0);

            $benefitsMonthly = round(array_sum(array_column($benefitComponents, 'calculatedValue')), 0);
            $benefitsAnnual = round(array_sum(array_column($benefitComponents, 'annualCalculatedValue')), 0);

            $netSalaryMonthly = round($grossMonthly - $totalDeductionsMonthly, 0);
            $netSalaryAnnual = round($grossAnnual - $totalDeductionsAnnual, 0);
            $totalCTCMonthly = round($grossMonthly + $benefitsMonthly, 0);
            $totalCTCAnnual = round($grossAnnual + $benefitsAnnual, 0);

            return response()->json([
                'status' => true,
                'message' => 'Payroll breakdown calculated successfully (V1)',
                'data' => [
                    'groupName' => $groupName,
                    'corpId' => $corpId,
                    'paygroupPuid' => $paygroupPuid,
                    'ctc' => round($ctc, 0),
                    'basicSalary' => round($basicSalary, 0),
                    'gross' => $grossComponents,
                    'deductions' => $deductionComponents,
                    'otherBenefitsAllowances' => $benefitComponents,
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
                        'deduction_components' => count($deductionComponents),
                        'benefit_components' => count($benefitComponents),
                        'total_components' => count($grossComponents) + count($deductionComponents) + count($benefitComponents)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in fetchCompletePayrollBreakdownImproved V1:', [
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

    // Calculate Basic salary from CTC using formula_builders
    private function calculateBasicFromCtc($formulaBuilders, $ctc)
    {
        $basicFormula = $formulaBuilders->get('Basic');

        if ($basicFormula) {
            $formulaType = strtolower($basicFormula->formula ?? '');
            $referenceValue = (float) ($basicFormula->referenceValue ?? 0);

            if ($formulaType === 'percent' && $referenceValue > 0) {
                return round(($referenceValue / 100) * $ctc, 0);
            } elseif ($formulaType === 'fixed' && $referenceValue > 0) {
                return round($referenceValue, 0);
            }
        }

        // Default: Basic = 40% of CTC if no formula defined
        return round(0.4 * $ctc, 0);
    }

    // Calculate component value using formula_builders scoped to paygroupPuid
    private function calculateComponentValueV1($componentName, $basicSalary, $formulaBuilders)
    {
        $fb = $formulaBuilders->get($componentName);

        if (!$fb) {
            return ['calculatedValue' => 0.0, 'formula' => 'N/A'];
        }

        $formulaType = strtolower($fb->formula ?? '');
        $refersTo = $fb->componentNameRefersTo ?? null;
        $referenceValue = (float) ($fb->referenceValue ?? 0);
        $calculatedValue = 0.0;
        $formula = $fb->formula;

        if ($formulaType === 'percent') {
            if (strtolower($refersTo ?? '') === 'basic' && $referenceValue > 0) {
                $calculatedValue = ($referenceValue / 100) * $basicSalary;
                $formula = $referenceValue . '% of Basic';
            } else {
                $calculatedValue = 0.0;
                $formula = $referenceValue . '% of ' . ($refersTo ?? 'Unknown');
            }
        } elseif ($formulaType === 'fixed') {
            $calculatedValue = $referenceValue;
            $formula = 'Fixed: ₹' . number_format($referenceValue, 2);
        } elseif ($formulaType === 'variable') {
            $calculatedValue = 0.0;
            $formula = 'Variable';
        } else {
            $calculatedValue = 0.0;
            $formula = 'Unknown Formula Type: ' . $fb->formula;
        }

        return [
            'calculatedValue' => round($calculatedValue, 0),
            'formula' => $formula
        ];
    }
}
