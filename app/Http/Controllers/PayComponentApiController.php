<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayComponent;

class PayComponentApiController extends Controller
{
    // Add or update PayComponent by corpId and puid
    public function storeOrUpdate(Request $request)
    {
        try {
            $request->validate([
                'corpId' => 'required|string',
                'puid' => 'required|string',
                'componentName' => 'required|string',
            ]);

            $data = $request->all();

            // Check if record exists first
            $existingComponent = PayComponent::where('corpId', $data['corpId'])
                ->where('puid', $data['puid'])
                ->first();

            if ($existingComponent) {
                // Update existing record
                $existingComponent->update($data);
                return response()->json([
                    'status' => true,
                    'message' => "PayComponent updated successfully",
                    'data' => $existingComponent
                ]);
            } else {
                // Create new record
                $payComponent = PayComponent::create($data);
                return response()->json([
                    'status' => true,
                    'message' => "PayComponent created successfully",
                    'data' => $payComponent
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // Fetch all PayComponents by corpId
    public function getByCorpId($corpId)
    {
        $components = PayComponent::where('corpId', $corpId)->get();
        return response()->json(['data' => $components]);
    }

    // Fetch PayComponent by puid (with status)
    public function getByPuid($puid)
    {
        $component = PayComponent::where('puid', $puid)->first();
        if (!$component) {
            return response()->json([
                'status' => false,
                'message' => 'PayComponent not found',
                'data' => (object)[]
            ], 404);
        }
        return response()->json(['status' => true, 'data' => $component]);
    }

    // Delete PayComponent by puid
    public function destroy($puid)
    {
        $component = PayComponent::where('puid', $puid)->first();
        if (!$component) {
            return response()->json(['message' => 'PayComponent not found'], 404);
        }
        $component->delete();
        return response()->json(['message' => 'PayComponent deleted successfully']);
    }
}
