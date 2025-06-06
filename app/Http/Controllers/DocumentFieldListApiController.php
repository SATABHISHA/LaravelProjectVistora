<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentFieldList;

class DocumentFieldListApiController extends Controller
{
    // Add document field
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'name' => 'required|string'
        ]);

        $field = DocumentFieldList::create($request->only('corp_id', 'name'));

        return response()->json(['message' => 'Document field added', 'data' => $field], 201);
    }

    // Fetch all by corp_id
    public function getByCorpId($corp_id)
    {
        $data = DocumentFieldList::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $data]);
    }

    // Delete by corp_id and id
    public function destroy($corp_id, $id)
    {
        $field = DocumentFieldList::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$field) {
            return response()->json(['message' => 'Document field not found'], 404);
        }
        $field->delete();
        return response()->json(['message' => 'Document field deleted']);
    }
}
