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
                'paygroupPuid' => 'required|string', // New field for paygroup_configurations
                'componentGroupName' => 'required|string',
                'componentName' => 'required|string',
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

            return response()->json([
                'status' => true,
                'message' => "Formula builder {$status} successfully",
                'data' => $formulaBuilder
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
}
