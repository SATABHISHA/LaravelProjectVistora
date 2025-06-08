<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SkillProficiency;

class SkillProficiencyApiController extends Controller
{
    // Add skill proficiency
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'skillproficiency_name' => 'required|string',
        ]);

        // Prevent duplicate skillproficiency_name for the same corp_id (case-insensitive)
        $exists = SkillProficiency::where('corp_id', $request->corp_id)
            ->whereRaw('LOWER(skillproficiency_name) = ?', [strtolower($request->skillproficiency_name)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Skill proficiency already exists for this corp_id'], 409);
        }

        $skillProficiency = SkillProficiency::create($request->all());

        return response()->json(['message' => 'Skill proficiency added successfully', 'skill_proficiency' => $skillProficiency], 201);
    }

    // Delete skill proficiency by corp_id and id
    public function destroy($corp_id, $id)
    {
        $skillProficiency = SkillProficiency::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$skillProficiency) {
            return response()->json(['message' => 'Skill proficiency not found'], 404);
        }

        $skillProficiency->delete();

        return response()->json(['message' => 'Skill proficiency deleted successfully']);
    }

    // Fetch all skill proficiencies by corp_id
    public function getByCorpId($corp_id)
    {
        $skillProficiencies = SkillProficiency::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $skillProficiencies]);
    }
}
