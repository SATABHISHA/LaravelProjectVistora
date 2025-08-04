<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ComponentType;

class ComponentTypeApiController extends Controller
{
    // Add or Update (Upsert)
    public function storeOrUpdate(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string',
            'componentType' => 'required|string',
        ]);

        // Check for duplicate
        $exists = ComponentType::where('corpId', $request->corpId)
            ->where('componentType', $request->componentType)
            ->first();

        if ($exists) {
            // Update
            $exists->update($request->all());
            return response()->json([
                'status' => true,
                'message' => 'Component type updated successfully.',
                'data' => $exists
            ]);
        } else {
            // Create
            $componentType = ComponentType::create($request->all());
            return response()->json([
                'status' => true,
                'message' => 'Component type added successfully.',
                'data' => $componentType
            ]);
        }
    }

    // Fetch by corpId
    public function getByCorpId($corpId)
    {
        $data = ComponentType::where('corpId', $corpId)->get();
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // Delete by corpId and id
    public function destroy($corpId, $id)
    {
        $componentType = ComponentType::where('corpId', $corpId)->where('id', $id)->first();

        if (!$componentType) {
            return response()->json([
                'status' => false,
                'message' => 'Component type not found.'
            ], 404);
        }

        $componentType->delete();

        return response()->json([
            'status' => true,
            'message' => 'Component type deleted successfully.'
        ]);
    }
}
