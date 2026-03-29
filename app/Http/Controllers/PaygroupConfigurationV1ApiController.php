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

            // Resolve all component values using dependency-aware calculation
            $resolvedValues = $this->resolveAllComponentValues($includedComponents, $formulaBuilders, $ctc);
            $basicSalary = $resolvedValues['Basic'] ?? 0;

            // Categorize components
            $grossComponents = [];
            $deductionComponents = [];
            $benefitComponents = [];

            foreach ($includedComponents as $componentName) {
                // Skip CTC as a display component — it's the input, not a salary component
                if (strtolower(trim($componentName)) === 'ctc') {
                    continue;
                }

                $payComponent = DB::table('pay_component_v1s')
                    ->where('componentName', $componentName)
                    ->where('corpId', $corpId)
                    ->first();

                if (!$payComponent) {
                    continue;
                }

                $calculatedValue = round($resolvedValues[$componentName] ?? 0, 0);
                $formula = $this->getFormulaDescription($componentName, $formulaBuilders);
                $annualCalculatedValue = round($calculatedValue * 12, 0);

                $componentResult = [
                    'componentName' => $componentName,
                    'payType' => $payComponent->payType,
                    'formula' => $formula,
                    'calculatedValue' => round($calculatedValue, 0),
                    'annualCalculatedValue' => round($annualCalculatedValue, 0)
                ];

                $payType = $payComponent->payType;

                $payTypeLower = strtolower($payType);

                if ($payTypeLower === 'addition' || $payTypeLower === 'earning' || $payTypeLower === 'earnings') {
                    $grossComponents[] = $componentResult;
                } elseif ($payTypeLower === 'deduction' || $payTypeLower === 'addition & deduction' || $payTypeLower === 'earning and deduction') {
                    $deductionComponents[] = $componentResult;
                } elseif ($payTypeLower === 'benefits' || $payTypeLower === 'benefit') {
                    $benefitComponents[] = $componentResult;
                }
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

    /**
     * Resolve all component values by building a dependency graph.
     * Supports two lookup modes:
     *   1. Forward: componentName has its own formula_builder entry
     *      → componentName = referenceValue% of componentNameRefersTo
     *   2. Reverse: component appears as componentNameRefersTo in another entry
     *      → component = referenceValue% of that entry's componentName
     *      e.g., formula_builder: componentName=Basic, refersTo=NPS, value=5
     *      means NPS = 5% of Basic
     */
    private function resolveAllComponentValues($includedComponents, $formulaBuilders, $ctc)
    {
        $resolved = [];
        $resolved['CTC'] = $ctc;

        // Step 1: Determine Basic salary from CTC
        $resolved['Basic'] = $this->deriveBasicFromCtc($formulaBuilders, $ctc);

        // Build reverse index: componentNameRefersTo → formula_builder entry
        // This allows lookup for components that don't have their own formula_builder entry
        // but are referenced by another component's formula
        $reverseIndex = [];
        foreach ($formulaBuilders as $fb) {
            $refersTo = trim($fb->componentNameRefersTo ?? '');
            if (!empty($refersTo)) {
                $reverseIndex[$refersTo] = $fb;
            }
        }

        // Step 2: Resolve all other components
        // Multiple passes to handle dependencies between components
        $maxPasses = 5;
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $allResolved = true;

            foreach ($includedComponents as $componentName) {
                $name = trim($componentName);
                if (isset($resolved[$name])) {
                    continue; // Already resolved
                }

                // Try direct lookup first (componentName has its own formula)
                $fb = $formulaBuilders->get($name);

                if ($fb) {
                    // Forward calculation: name = referenceValue% of refersTo
                    $formulaType = strtolower($fb->formula ?? '');
                    $refersTo = trim($fb->componentNameRefersTo ?? '');
                    $referenceValue = (float) ($fb->referenceValue ?? 0);

                    if ($formulaType === 'fixed') {
                        $resolved[$name] = round($referenceValue, 0);
                    } elseif ($formulaType === 'variable') {
                        $resolved[$name] = 0;
                    } elseif ($formulaType === 'percent' && $referenceValue > 0) {
                        if (isset($resolved[$refersTo])) {
                            $resolved[$name] = round(($referenceValue / 100) * $resolved[$refersTo], 0);
                        } else {
                            // Try case-insensitive match
                            $found = false;
                            foreach ($resolved as $key => $val) {
                                if (strtolower($key) === strtolower($refersTo)) {
                                    $resolved[$name] = round(($referenceValue / 100) * $val, 0);
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $allResolved = false; // Will try again next pass
                            }
                        }
                    } else {
                        $resolved[$name] = 0;
                    }
                } elseif (isset($reverseIndex[$name])) {
                    // Reverse lookup: this component is referenced by another formula_builder
                    // e.g., Basic's entry has componentNameRefersTo=NPS
                    // means NPS = referenceValue% of Basic
                    $reverseFb = $reverseIndex[$name];
                    $formulaType = strtolower($reverseFb->formula ?? '');
                    $sourceComponent = trim($reverseFb->componentName ?? '');
                    $referenceValue = (float) ($reverseFb->referenceValue ?? 0);

                    if ($formulaType === 'fixed') {
                        $resolved[$name] = round($referenceValue, 0);
                    } elseif ($formulaType === 'variable') {
                        $resolved[$name] = 0;
                    } elseif ($formulaType === 'percent' && $referenceValue > 0) {
                        if (isset($resolved[$sourceComponent])) {
                            // component = referenceValue% of sourceComponent
                            $resolved[$name] = round(($referenceValue / 100) * $resolved[$sourceComponent], 0);
                        } else {
                            // Try case-insensitive match
                            $found = false;
                            foreach ($resolved as $key => $val) {
                                if (strtolower($key) === strtolower($sourceComponent)) {
                                    $resolved[$name] = round(($referenceValue / 100) * $val, 0);
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $allResolved = false; // Will try again next pass
                            }
                        }
                    } else {
                        $resolved[$name] = 0;
                    }
                } else {
                    // No formula found at all
                    $resolved[$name] = 0;
                }
            }

            if ($allResolved) {
                break;
            }
        }

        return $resolved;
    }

    /**
     * Derive Basic salary from CTC using formula_builders.
     * Handles both directions:
     *   - Basic's formula says "X% of CTC" → Basic = X% of CTC
     *   - CTC's formula says "X% of Basic" → Basic = CTC / (X/100)
     *   - Basic's formula is "fixed" → use the fixed value
     *   - Default fallback: Basic = 40% of CTC
     */
    private function deriveBasicFromCtc($formulaBuilders, $ctc)
    {
        $basicFb = $formulaBuilders->get('Basic');
        $ctcFb = $formulaBuilders->get('CTC');

        // Case 1: Basic formula defines relationship to CTC directly
        if ($basicFb) {
            $formulaType = strtolower($basicFb->formula ?? '');
            $refersTo = strtolower(trim($basicFb->componentNameRefersTo ?? ''));
            $referenceValue = (float) ($basicFb->referenceValue ?? 0);

            if ($formulaType === 'percent' && $refersTo === 'ctc' && $referenceValue > 0) {
                return round(($referenceValue / 100) * $ctc, 0);
            }
            if ($formulaType === 'fixed' && $referenceValue > 0) {
                return round($referenceValue, 0);
            }
        }

        // Case 2: CTC formula defines relationship to Basic (reverse calculation)
        // e.g., CTC = 70% of Basic → Basic = CTC / 0.70
        if ($ctcFb) {
            $formulaType = strtolower($ctcFb->formula ?? '');
            $refersTo = strtolower(trim($ctcFb->componentNameRefersTo ?? ''));
            $referenceValue = (float) ($ctcFb->referenceValue ?? 0);

            if ($formulaType === 'percent' && $refersTo === 'basic' && $referenceValue > 0) {
                return round($ctc / ($referenceValue / 100), 0);
            }
        }

        // Default: Basic = 40% of CTC
        return round(0.4 * $ctc, 0);
    }

    /**
     * Get human-readable formula description for a component.
     */
    private function getFormulaDescription($componentName, $formulaBuilders)
    {
        if (strtolower(trim($componentName)) === 'basic') {
            return 'Basic';
        }

        // Direct lookup: componentName has its own formula_builder entry
        $fb = $formulaBuilders->get($componentName);
        if ($fb) {
            $formulaType = strtolower($fb->formula ?? '');
            $refersTo = $fb->componentNameRefersTo ?? 'Unknown';
            $referenceValue = (float) ($fb->referenceValue ?? 0);

            if ($formulaType === 'percent' && $referenceValue > 0) {
                return $referenceValue . '% of ' . $refersTo;
            } elseif ($formulaType === 'fixed') {
                return 'Fixed: ₹' . number_format($referenceValue, 2);
            } elseif ($formulaType === 'variable') {
                return 'Variable';
            }

            return $fb->formula ?? 'N/A';
        }

        // Reverse lookup: check if this component appears as componentNameRefersTo
        // in another formula_builder entry (e.g., Basic's entry has refersTo=NPS → NPS = 5% of Basic)
        foreach ($formulaBuilders as $entry) {
            if (trim($entry->componentNameRefersTo ?? '') === $componentName) {
                $formulaType = strtolower($entry->formula ?? '');
                $referenceValue = (float) ($entry->referenceValue ?? 0);
                $sourceComponent = $entry->componentName;

                if ($formulaType === 'percent' && $referenceValue > 0) {
                    return $referenceValue . '% of ' . $sourceComponent;
                } elseif ($formulaType === 'fixed') {
                    return 'Fixed: ₹' . number_format($referenceValue, 2);
                } elseif ($formulaType === 'variable') {
                    return 'Variable';
                }

                return $entry->formula ?? 'N/A';
            }
        }

        return 'N/A';
    }
}
