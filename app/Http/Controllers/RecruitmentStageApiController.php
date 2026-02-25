<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RecruitmentStageApiController extends Controller
{
    // List all stages for a corp
    public function index($corp_id)
    {
        $stages = RecruitmentStage::where('corp_id', $corp_id)
            ->orderBy('stage_order')
            ->get();

        return response()->json(['status' => true, 'data' => $stages]);
    }

    // Show single stage
    public function show($corp_id, $id)
    {
        $stage = RecruitmentStage::where('corp_id', $corp_id)
            ->where('id', $id)
            ->first();

        if (!$stage) {
            return response()->json(['status' => false, 'message' => 'Stage not found.'], 404);
        }

        return response()->json(['status' => true, 'data' => $stage]);
    }

    // Create stage
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id'    => 'required|string',
            'stage_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $stage = RecruitmentStage::create($request->all());
        return response()->json(['status' => true, 'message' => 'Stage created.', 'data' => $stage], 201);
    }

    // Update stage
    public function update(Request $request, $corp_id, $id)
    {
        $stage = RecruitmentStage::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $stage->update($request->all());
        return response()->json(['status' => true, 'message' => 'Stage updated.', 'data' => $stage]);
    }

    // Delete stage
    public function destroy($corp_id, $id)
    {
        $stage = RecruitmentStage::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $stage->delete();
        return response()->json(['status' => true, 'message' => 'Stage deleted.']);
    }
}
