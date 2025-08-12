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

            // Check for duplicate componentName for the same corpId (excluding current puid if updating)
            $duplicateCheck = PayComponent::where('corpId', $data['corpId'])
                ->where('componentName', $data['componentName'])
                ->where('puid', '!=', $data['puid']) // Exclude current record when updating
                ->exists();

            if ($duplicateCheck) {
                return response()->json([
                    'status' => false,
                    'message' => 'Component with this name already exists for this corpId.'
                ], 409);
            }

            $payComponent = PayComponent::updateOrCreate(
                ['corpId' => $data['corpId'], 'puid' => $data['puid']],
                $data
            );

            $status = $payComponent->wasRecentlyCreated ? 'created' : 'updated';

            return response()->json([
                'message' => "PayComponent {$status} successfully",
                'status' => $status,
                'pay_component' => $payComponent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
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
                'status' => 'false',
                'message' => 'PayComponent not found',
                'data' => (object)[]
            ],
            404);
        }
        return response()->json(['status' => 'true', 'data' => $component]);
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
