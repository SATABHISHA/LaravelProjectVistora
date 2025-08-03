<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayComponent;

class PayComponentApiController extends Controller
{
    // Add or update PayComponent by corpId and puid
    public function storeOrUpdate(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string',
            'puid' => 'required|string',
            'componentName' => 'required|string',
            // Add other validation rules as needed
        ]);

        $data = $request->all();

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
            return response()->json(['status' => 'false', 'message' => 'PayComponent not found'], 404);
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
