<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfirmationStatus;

class ConfirmationStatusApiController extends Controller
{
    // Add a new confirmation status
    public function store(Request $request)
    {
        $validated = $request->validate([
            'corp_id' => 'required|string',
            'confirmation_status' => 'required|string',
        ]);
        $status = ConfirmationStatus::create($validated);
        return response()->json($status, 201);
    }

    // Update by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $status = ConfirmationStatus::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $status->update($request->only(['confirmation_status']));
        return response()->json($status);
    }

    // Delete by corp_id and id
    public function destroy($corp_id, $id)
    {
        $status = ConfirmationStatus::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $status->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // Fetch all by corp_id
    public function getByCorpId($corp_id)
    {
        $statuses = ConfirmationStatus::where('corp_id', $corp_id)->get();
        return response()->json($statuses);
    }
}
