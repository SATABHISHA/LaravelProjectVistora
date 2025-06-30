<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeNomineeDetail;

class EmployeeNomineeDetailApiController extends Controller
{
    // Helper to generate a random light hex color
    private function randomLightHexColor()
    {
        $r = rand(180, 255);
        $g = rand(180, 255);
        $b = rand(180, 255);
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }

    // Add Nominee
    public function store(Request $request)
    {
        $data = $request->all();
        $data['color'] = $this->randomLightHexColor();

        $nominee = EmployeeNomineeDetail::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Nominee added',
            'data' => $nominee
        ], 201);
    }

    // Update Nominee by corp_id, empcode, id
    public function update(Request $request, $corp_id, $empcode, $id)
    {
        $nominee = EmployeeNomineeDetail::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();

        if (!$nominee) {
            return response()->json(['status' => false, 'message' => 'Nominee not found'], 404);
        }

        $nominee->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Nominee updated',
            'data' => $nominee
        ]);
    }

    // Delete Nominee by corp_id, empcode, id
    public function destroy($corp_id, $empcode, $id)
    {
        $nominee = EmployeeNomineeDetail::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();

        if (!$nominee) {
            return response()->json(['status' => false, 'message' => 'Nominee not found'], 404);
        }

        $nominee->delete();

        return response()->json(['status' => true, 'message' => 'Nominee deleted']);
    }

    // Fetch all Nominees by corp_id, empcode
    public function show($corp_id, $empcode)
    {
        $nominees = EmployeeNomineeDetail::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->get();

        if ($nominees->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No nominees found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $nominees
        ]);
    }
}
