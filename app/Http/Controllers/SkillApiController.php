<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Skill;

class SkillApiController extends Controller
{
    // Add skill
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'skill_name' => 'required|string',
        ]);

        // Prevent duplicate skill_name for the same corp_id (case-insensitive)
        $exists = Skill::where('corp_id', $request->corp_id)
            ->whereRaw('LOWER(skill_name) = ?', [strtolower($request->skill_name)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Skill already exists for this corp_id'], 409);
        }

        $skill = Skill::create($request->all());

        return response()->json(['message' => 'Skill added successfully', 'skill' => $skill], 201);
    }

    // Delete skill by corp_id and id
    public function destroy($corp_id, $id)
    {
        $skill = Skill::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }

        $skill->delete();

        return response()->json(['message' => 'Skill deleted successfully']);
    }

    // Fetch all skills by corp_id
    public function getByCorpId($corp_id)
    {
        $skills = Skill::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $skills]);
    }
}
