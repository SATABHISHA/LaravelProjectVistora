<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FormulaBuilder;

class FormulaBuilderApiController extends Controller
{
    // Add or Update Formula Builder
    public function storeOrUpdate(Request $request)
    {
        try {
            $request->validate([
                'corpId' => 'required|string',
                'puid' => 'required|string',
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
    public function destroy($puid)
    {
        try {
            $formula = FormulaBuilder::where('puid', $puid)->first();
            
            if (!$formula) {
                return response()->json([
                    'status' => false,
                    'message' => 'Formula builder not found'
                ], 404);
            }

            $formula->delete();

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
