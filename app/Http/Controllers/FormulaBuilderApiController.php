<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FormulaBuilder;
use App\Models\PaygroupConfiguration;

class FormulaBuilderApiController extends Controller
{
    // Add or Update Formula Builder
    public function storeOrUpdate(Request $request)
    {
        try {
            $request->validate([
                'corpId' => 'required|string',
                'puid' => 'required|string',
                'paygroupPuid' => 'required|string',
                'componentGroupName' => 'required|string',
                'componentName' => 'required|string',
                'componentNameRefersTo' => 'required|string',
                'referenceValue' => 'nullable|string', // Added validation for referenceValue
                'formula' => 'required|string',
            ]);

            $data = $request->all();

            // Process formula: remove spaces and convert to lowercase
            $data['formula'] = strtolower(str_replace(' ', '', $data['formula']));

            $formulaBuilder = FormulaBuilder::updateOrCreate(
                ['corpId' => $data['corpId'], 'puid' => $data['puid']],
                $data
            );

            // Update FormulaConfiguredYn to 1 in paygroup_configurations table using paygroupPuid
            PaygroupConfiguration::where('puid', $data['paygroupPuid'])
                ->update(['FormulaConfiguredYn' => 1]);

            $status = $formulaBuilder->wasRecentlyCreated ? 'created' : 'updated';

            // Calculate value for percent formulas and add to response
            $responseData = $formulaBuilder->toArray();
            $responseData['calculatedValue'] = $this->calculateFormulaValue($formulaBuilder);

            return response()->json([
                'status' => true,
                'message' => "Formula builder {$status} successfully",
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // Fetch by corpId and componentGroupName
    public function fetchByCorpIdAndGroup($corpId, $componentGroupName)
    {
        try {
            $formulas = FormulaBuilder::where('corpId', $corpId)
                ->where('componentGroupName', $componentGroupName)
                ->get();

            // Add calculated values to each formula
            $formulas = $formulas->map(function($formula) {
                $formulaArray = $formula->toArray();
                $formulaArray['calculatedValue'] = $this->calculateFormulaValue($formula);
                return $formulaArray;
            });

            return response()->json([
                'status' => true,
                'data' => $formulas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete by puid
    public function destroy($puid, $paygroupPuid)
    {
        try {
            $formula = FormulaBuilder::where('puid', $puid)->first();
            
            if (!$formula) {
                return response()->json([
                    'status' => false,
                    'message' => 'Formula builder not found'
                ], 404);
            }

            // Store the componentGroupName and corpId before deletion
            $componentGroupName = $formula->componentGroupName;
            $corpId = $formula->corpId;

            $formula->delete();

            // Check if any other formulas exist for the same componentGroupName and corpId
            $remainingFormulas = FormulaBuilder::where('corpId', $corpId)
                ->where('componentGroupName', $componentGroupName)
                ->exists();

            // Only reset FormulaConfiguredYn to 0 if no other formulas exist for this componentGroupName
            if (!$remainingFormulas) {
                PaygroupConfiguration::where('puid', $paygroupPuid)
                    ->update(['FormulaConfiguredYn' => 0]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Formula builder deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate value based on formula
     * For percent formulas, calculate percentage of referenceValue
     * For other formulas, return 0
     */
    private function calculateFormulaValue($formula)
    {
        // Default return value if not a percent formula
        $calculatedValue = 0;

        // Check if formula contains 'percent'
        if (strpos($formula->formula, 'percent') !== false) {
            // Extract percentage value (e.g., from "30percent" get 30)
            preg_match('/(\d+(?:\.\d+)?)percent/', $formula->formula, $matches);
            
            if (isset($matches[1])) {
                $percentValue = (float)$matches[1];
                
                // If reference value exists, calculate the percentage
                if (!empty($formula->referenceValue) && is_numeric($formula->referenceValue)) {
                    $referenceValue = (float)$formula->referenceValue;
                    $calculatedValue = ($percentValue / 100) * $referenceValue;
                }
            }
        }

        return $calculatedValue;
    }
}
