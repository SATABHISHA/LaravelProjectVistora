<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeSkill;

class EmployeeSkillApiController extends Controller
{
    // Add Skill
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'empcode' => 'required|string',
            'SkillName' => 'required|string',
            'Proficiency' => 'required|string',
        ]);
        $skill = EmployeeSkill::create($request->all());
        return response()->json(['message' => 'Skill added', 'data' => $skill], 201);
    }

    // Update Skill by corp_id, empcode, id
    public function update(Request $request, $corp_id, $empcode, $id)
    {
        $skill = EmployeeSkill::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();
        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }
        $skill->update($request->all());
        return response()->json(['message' => 'Skill updated', 'data' => $skill]);
    }

    // Delete Skill by corp_id, empcode, id
    public function destroy($corp_id, $empcode, $id)
    {
        $skill = EmployeeSkill::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->where('id', $id)
            ->first();
        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }
        $skill->delete();
        return response()->json(['message' => 'Skill deleted']);
    }

    // Fetch all Skills by corp_id, empcode
    public function show($corp_id, $empcode)
    {
        $skills = EmployeeSkill::where('corp_id', $corp_id)
            ->where('empcode', $empcode)
            ->get();

        if ($skills->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No skills found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $skills
        ]);
    }
}
