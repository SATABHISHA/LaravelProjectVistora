<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchApiController extends Controller
{
    // Add branch
    public function store(Request $request)
    {
        $validated = $request->validate([
            'corp_id' => 'required|string',
            'branch' => 'required|string',
        ]);
        $branch = Branch::create($validated);
        return response()->json($branch, 201);
    }

    // Update branch by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $branch = Branch::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $branch->update($request->only(['branch']));
        return response()->json($branch);
    }

    // Delete branch by corp_id and id
    public function destroy($corp_id, $id)
    {
        $branch = Branch::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $branch->delete();
        return response()->json(['message' => 'Branch deleted']);
    }

    // Fetch all branches by corp_id
    public function getByCorpId($corp_id)
    {
        $branches = Branch::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $branches]);
    }
}
