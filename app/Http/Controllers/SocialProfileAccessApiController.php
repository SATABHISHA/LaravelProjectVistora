<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SocialProfileAccess;

class SocialProfileAccessApiController extends Controller
{
    // Add social profile access (no duplicate profile_menu_name for corp_id)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'profile_menu_name' => 'required|string',
        ]);

        $exists = SocialProfileAccess::where('corp_id', $request->corp_id)
            ->where('profile_menu_name', $request->profile_menu_name)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Profile menu name already exists for this corp_id'], 409);
        }

        $access = SocialProfileAccess::create($request->all());
        return response()->json(['message' => 'Social profile access added', 'data' => $access], 201);
    }

    // Fetch by corp_id
    public function getByCorpId($corp_id)
    {
        $data = SocialProfileAccess::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $data]);
    }

    // Update by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $access = SocialProfileAccess::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$access) {
            return response()->json(['message' => 'Social profile access not found'], 404);
        }
        $access->update($request->all());
        return response()->json(['message' => 'Social profile access updated', 'data' => $access]);
    }

    // Delete by corp_id and id
    public function destroy($corp_id, $id)
    {
        $access = SocialProfileAccess::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$access) {
            return response()->json(['message' => 'Social profile access not found'], 404);
        }
        $access->delete();
        return response()->json(['message' => 'Social profile access deleted']);
    }
}
