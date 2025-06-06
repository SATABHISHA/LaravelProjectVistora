<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentFormList;

class DocumentFormListApiController extends Controller
{
    // Add document form
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'name' => 'required|string'
        ]);

        $form = DocumentFormList::create($request->only('corp_id', 'name'));

        return response()->json(['message' => 'Document form added', 'data' => $form], 201);
    }

    // Fetch all by corp_id
    public function getByCorpId($corp_id)
    {
        $data = DocumentFormList::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $data]);
    }

    // Delete by corp_id and id
    public function destroy($corp_id, $id)
    {
        $form = DocumentFormList::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$form) {
            return response()->json(['message' => 'Document form not found'], 404);
        }
        $form->delete();
        return response()->json(['message' => 'Document form deleted']);
    }
}
