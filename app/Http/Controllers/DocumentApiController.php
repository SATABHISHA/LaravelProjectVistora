<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class DocumentApiController extends Controller
{
    // Add document with file upload validation
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'name' => 'required|string',
            'doc_type' => 'required|string',
            'track_send_alert_yn' => 'integer',
            'candidate_view_yn' => 'integer',
            'candidate_edit_yn' => 'integer',
            'emp_view_yn' => 'integer',
            'emp_edit_yn' => 'integer',
            'mandatory_employee_yn' => 'integer',
            'mandatory_candidate_yn' => 'integer',
            'mandatory_to_convert_emp_yn' => 'integer',
            'mandatory_upcoming_join_yn' => 'integer',
            'form_list' => 'nullable|string',
            'field_list' => 'nullable|string',
            'doc_upload' => 'nullable|file|max:5120|mimes:pdf,xls,xlsx'
        ], [
            'doc_upload.max' => 'The document must not be greater than 5MB.',
            'doc_upload.mimes' => 'The document must be a file of type: pdf, xls, xlsx.',
        ]);

        $path = null;
        if ($request->hasFile('doc_upload')) {
            $path = $request->file('doc_upload')->store('documents', 'public');
        }

        $document = Document::create([
            'corp_id' => $request->corp_id,
            'name' => $request->name,
            'doc_type' => $request->doc_type,
            'track_send_alert_yn' => $request->track_send_alert_yn ?? 0,
            'candidate_view_yn' => $request->candidate_view_yn ?? 0,
            'candidate_edit_yn' => $request->candidate_edit_yn ?? 0,
            'emp_view_yn' => $request->emp_view_yn ?? 0,
            'emp_edit_yn' => $request->emp_edit_yn ?? 0,
            'mandatory_employee_yn' => $request->mandatory_employee_yn ?? 0,
            'mandatory_candidate_yn' => $request->mandatory_candidate_yn ?? 0,
            'mandatory_to_convert_emp_yn' => $request->mandatory_to_convert_emp_yn ?? 0,
            'mandatory_upcoming_join_yn' => $request->mandatory_upcoming_join_yn ?? 0,
            'form_list' => $request->form_list,
            'field_list' => $request->field_list,
            'doc_upload' => $path,
        ]);

        return response()->json(['message' => 'Document added', 'data' => $document], 201);
    }

    // Fetch documents by corp_id
    public function getByCorpId($corp_id)
    {
        $data = Document::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $data]);
    }

    // Delete document by corp_id and id
    public function destroy($corp_id, $id)
    {
        $document = Document::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }
        // Optionally delete the file from storage
        if ($document->doc_upload) {
            Storage::disk('public')->delete($document->doc_upload);
        }
        $document->delete();
        return response()->json(['message' => 'Document deleted']);
    }

    // Update document
    public function update(Request $request, $corp_id, $id)
    {
        $document = \App\Models\Document::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $request->validate([
            'corp_id' => 'sometimes|required|string',
            'name' => 'sometimes|required|string',
            'doc_type' => 'sometimes|required|string',
            'track_send_alert_yn' => 'sometimes|integer',
            'candidate_view_yn' => 'sometimes|integer',
            'candidate_edit_yn' => 'sometimes|integer',
            'emp_view_yn' => 'sometimes|integer',
            'emp_edit_yn' => 'sometimes|integer',
            'mandatory_employee_yn' => 'sometimes|integer',
            'mandatory_candidate_yn' => 'sometimes|integer',
            'mandatory_to_convert_emp_yn' => 'sometimes|integer',
            'mandatory_upcoming_join_yn' => 'sometimes|integer',
            'form_list' => 'sometimes|nullable|string',
            'field_list' => 'sometimes|nullable|string',
            'doc_upload' => 'sometimes|nullable|file|max:5120|mimes:pdf,xls,xlsx'
        ], [
            'doc_upload.max' => 'The document must not be greater than 5MB.',
            'doc_upload.mimes' => 'The document must be a file of type: pdf, xls, xlsx.',
        ]);

        $path = $document->doc_upload;
        if ($request->hasFile('doc_upload')) {
            // Delete the old file from storage
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $path = $request->file('doc_upload')->store('documents', 'public');
        }

        $document->update([
            'corp_id' => $request->corp_id ?? $document->corp_id,
            'name' => $request->name ?? $document->name,
            'doc_type' => $request->doc_type ?? $document->doc_type,
            'track_send_alert_yn' => $request->track_send_alert_yn ?? $document->track_send_alert_yn,
            'candidate_view_yn' => $request->candidate_view_yn ?? $document->candidate_view_yn,
            'candidate_edit_yn' => $request->candidate_edit_yn ?? $document->candidate_edit_yn,
            'emp_view_yn' => $request->emp_view_yn ?? $document->emp_view_yn,
            'emp_edit_yn' => $request->emp_edit_yn ?? $document->emp_edit_yn,
            'mandatory_employee_yn' => $request->mandatory_employee_yn ?? $document->mandatory_employee_yn,
            'mandatory_candidate_yn' => $request->mandatory_candidate_yn ?? $document->mandatory_candidate_yn,
            'mandatory_to_convert_emp_yn' => $request->mandatory_to_convert_emp_yn ?? $document->mandatory_to_convert_emp_yn,
            'mandatory_upcoming_join_yn' => $request->mandatory_upcoming_join_yn ?? $document->mandatory_upcoming_join_yn,
            'form_list' => $request->form_list ?? $document->form_list,
            'field_list' => $request->field_list ?? $document->field_list,
            'doc_upload' => $path,
        ]);

        return response()->json(['message' => 'Document updated successfully', 'document' => $document]);
    }
}
