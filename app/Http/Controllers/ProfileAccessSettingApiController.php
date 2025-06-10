<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProfileAccessSetting;

class ProfileAccessSettingApiController extends Controller
{
    // Add profile access setting (no duplicate profile_tab_name for corp_id)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'profile_tab_name' => 'required|string',
        ]);

        $exists = ProfileAccessSetting::where('corp_id', $request->corp_id)
            ->where('profile_tab_name', $request->profile_tab_name)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Profile tab name already exists for this corp_id'], 409);
        }

        $setting = ProfileAccessSetting::create($request->all());
        return response()->json(['message' => 'Profile access setting added', 'data' => $setting], 201);
    }

    // Fetch by corp_id (response inside data)
    public function getByCorpId($corp_id)
    {
        $data = ProfileAccessSetting::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $data]);
    }

    // Update by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $setting = ProfileAccessSetting::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$setting) {
            return response()->json(['message' => 'Profile access setting not found'], 404);
        }
        $setting->update($request->all());
        return response()->json(['message' => 'Profile access setting updated', 'data' => $setting]);
    }

    // Delete by corp_id and id
    public function destroy($corp_id, $id)
    {
        $setting = ProfileAccessSetting::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$setting) {
            return response()->json(['message' => 'Profile access setting not found'], 404);
        }
        $setting->delete();
        return response()->json(['message' => 'Profile access setting deleted']);
    }
}
