<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Blacklist;

class BlacklistApiController extends Controller
{
    // Add blacklist
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'blacklist_name' => 'required|string',
        ]);

        // Check for duplicate blacklist_name under the same corp_id (case-insensitive)
        $exists = Blacklist::where('corp_id', $request->corp_id)
            ->whereRaw('LOWER(blacklist_name) = ?', [strtolower($request->blacklist_name)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Blacklist name already exists for this corp_id'], 409);
        }

        $blacklist = Blacklist::create($request->all());

        return response()->json(['message' => 'Blacklist added successfully', 'blacklist' => $blacklist], 201);
    }

    // Delete blacklist by corp_id and id
    public function destroy($corp_id, $id)
    {
        $blacklist = Blacklist::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$blacklist) {
            return response()->json(['message' => 'Blacklist not found'], 404);
        }

        $blacklist->delete();

        return response()->json(['message' => 'Blacklist deleted successfully']);
    }

    // Fetch all blacklists by corp_id
    public function getByCorpId($corp_id)
    {
        $blacklists = Blacklist::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $blacklists]);
    }
}
