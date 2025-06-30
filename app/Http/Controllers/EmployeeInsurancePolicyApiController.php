<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeInsurancePolicy;

class EmployeeInsurancePolicyApiController extends Controller
{
    // Helper to generate a random light hex color
    private function randomLightColor()
    {
        $r = rand(180, 255);
        $g = rand(180, 255);
        $b = rand(180, 255);
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }

    // Add Policy
    public function store(Request $request)
    {
        $data = $request->all();
        $data['color'] = $this->randomLightColor();

        $policy = EmployeeInsurancePolicy::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Policy added',
            'data' => $policy
        ], 201);
    }

    // Update Policy by corp_id, empcode, id
    public function update(Request $request, $corp_id, $empcode, $id)
    {
        $policy = EmployeeInsurancePolicy::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();

        if (!$policy) {
            return response()->json(['status' => false, 'message' => 'Policy not found'], 404);
        }

        $policy->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Policy updated',
            'data' => $policy
        ]);
    }

    // Delete Policy by corp_id, empcode, id
    public function destroy($corp_id, $empcode, $id)
    {
        $policy = EmployeeInsurancePolicy::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();

        if (!$policy) {
            return response()->json(['status' => false, 'message' => 'Policy not found'], 404);
        }

        $policy->delete();

        return response()->json(['status' => true, 'message' => 'Policy deleted']);
    }

    // Fetch all Policies by corp_id, empcode
    public function show($corp_id, $empcode)
    {
        $policies = EmployeeInsurancePolicy::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->get();

        if ($policies->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No policies found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $policies
        ]);
    }
}
