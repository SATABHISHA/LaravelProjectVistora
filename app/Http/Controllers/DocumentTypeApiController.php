<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentType;

class DocumentTypeApiController extends Controller
{
    // Add document type
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'name' => 'required|string'
        ]);

        $docType = DocumentType::create($request->only('corp_id', 'name'));

        return response()->json(['message' => 'Document type added', 'document_type' => $docType], 201);
    }

    // Fetch document types by corp_id
    public function getByCorpId($corp_id)
    {
        $docTypes = DocumentType::where('corp_id', $corp_id)->get();
        return response()->json(['data'=>$docTypes]);
    }

    // Delete document type by corp_id and id
    public function destroy($corp_id, $id)
    {
        $docType = DocumentType::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$docType) {
            return response()->json(['message' => 'Document type not found'], 404);
        }
        $docType->delete();
        return response()->json(['message' => 'Document type deleted']);
    }
}
