<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaygroupConfigurationV1;

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

    // Delete PaygroupConfigurationV1 by puid
    public function destroy($puid)
    {
        $paygroup = PaygroupConfigurationV1::where('puid', $puid)->first();

        if (!$paygroup) {
            return response()->json([
                'status' => false,
                'message' => 'PaygroupConfigurationV1 not found'
            ], 404);
        }

        $paygroup->delete();

        return response()->json([
            'status' => true,
            'message' => 'PaygroupConfigurationV1 deleted successfully'
        ]);
    }
}
