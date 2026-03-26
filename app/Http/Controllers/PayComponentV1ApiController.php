<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayComponentV1;

class PayComponentV1ApiController extends Controller
{
    // Add or update PayComponentV1 by puid
    public function storeOrUpdate(Request $request)
    {
        try {
            $request->validate([
                'corpId' => 'required|string',
                'componentName' => 'required|string',
                'companyName' => 'required|string',
                'payType' => 'required|string',
            ]);

            $data = $request->all();
            $puid = $data['puid'] ?? null;

            if ($puid) {
                // Update existing record
                $component = PayComponentV1::where('puid', $puid)->first();

                if (!$component) {
                    return response()->json([
                        'status' => false,
                        'message' => 'PayComponentV1 not found for the given puid.'
                    ], 404);
                }

                // Check for duplicate componentName for the same corpId (excluding current record)
                $duplicate = PayComponentV1::where('corpId', $data['corpId'])
                    ->where('componentName', $data['componentName'])
                    ->where('puid', '!=', $puid)
                    ->exists();

                if ($duplicate) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Component with this name already exists for this corpId.'
                    ], 409);
                }

                $component->update($data);

                return response()->json([
                    'status' => true,
                    'message' => 'PayComponentV1 updated successfully',
                    'data' => $component
                ]);
            } else {
                // Create new record (puid auto-generated)
                $duplicate = PayComponentV1::where('corpId', $data['corpId'])
                    ->where('componentName', $data['componentName'])
                    ->exists();

                if ($duplicate) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Component with this name already exists for this corpId.'
                    ], 409);
                }

                unset($data['puid']); // Ensure puid is auto-generated
                $component = PayComponentV1::create($data);

                return response()->json([
                    'status' => true,
                    'message' => 'PayComponentV1 created successfully',
                    'data' => $component
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

    // Fetch all PayComponentV1 by corpId
    public function getByCorpId($corpId)
    {
        $components = PayComponentV1::where('corpId', $corpId)->get();
        return response()->json([
            'status' => true,
            'data' => $components
        ]);
    }

    // Delete PayComponentV1 by puid
    public function destroy($puid)
    {
        $component = PayComponentV1::where('puid', $puid)->first();

        if (!$component) {
            return response()->json([
                'status' => false,
                'message' => 'PayComponentV1 not found'
            ], 404);
        }

        $component->delete();

        return response()->json([
            'status' => true,
            'message' => 'PayComponentV1 deleted successfully'
        ]);
    }
}
