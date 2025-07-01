<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubBranch;

class SubBranchApiController extends Controller
{
    // Add a new subbranch
    public function store(Request $request)
    {
        $validated = $request->validate([
            'corp_id' => 'required|string',
            'subbranch' => 'required|string',
        ]);
        $subbranch = SubBranch::create($validated);
        return response()->json($subbranch, 201);
    }

    // Update subbranch by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $subbranch = SubBranch::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $subbranch->update($request->only(['subbranch']));
        return response()->json($subbranch);
    }

    // Delete subbranch by corp_id and id
    public function destroy($corp_id, $id)
    {
        $subbranch = SubBranch::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $subbranch->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // Fetch all subbranches by corp_id
    public function getByCorpId($corp_id)
    {
        $subbranches = SubBranch::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $subbranches]);
    }
}
