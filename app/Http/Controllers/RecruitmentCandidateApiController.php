<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentCandidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RecruitmentCandidateApiController extends Controller
{
    // List all candidates for a corp
    public function index($corp_id)
    {
        $candidates = RecruitmentCandidate::where('corp_id', $corp_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $candidates]);
    }

    // Show single candidate
    public function show($corp_id, $id)
    {
        $candidate = RecruitmentCandidate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->first();

        if (!$candidate) {
            return response()->json(['status' => false, 'message' => 'Candidate not found.'], 404);
        }

        return response()->json(['status' => true, 'data' => $candidate]);
    }

    // Create candidate (with optional resume upload)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id'    => 'required|string',
            'first_name' => 'required|string|max:255',
            'email'      => 'nullable|email',
            'resume'     => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->except('resume');

        if ($request->hasFile('resume')) {
            $path = $request->file('resume')->store(
                'recruitment/resumes/' . $request->corp_id,
                'public'
            );
            $data['resume_path'] = $path;
        }

        $candidate = RecruitmentCandidate::create($data);
        return response()->json(['status' => true, 'message' => 'Candidate created.', 'data' => $candidate], 201);
    }

    // Update candidate
    public function update(Request $request, $corp_id, $id)
    {
        $candidate = RecruitmentCandidate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->except('resume');

        if ($request->hasFile('resume')) {
            // Delete old resume
            if ($candidate->resume_path) {
                Storage::disk('public')->delete($candidate->resume_path);
            }
            $path = $request->file('resume')->store(
                'recruitment/resumes/' . $corp_id,
                'public'
            );
            $data['resume_path'] = $path;
        }

        $candidate->update($data);
        return response()->json(['status' => true, 'message' => 'Candidate updated.', 'data' => $candidate]);
    }

    // Delete candidate
    public function destroy($corp_id, $id)
    {
        $candidate = RecruitmentCandidate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        if ($candidate->resume_path) {
            Storage::disk('public')->delete($candidate->resume_path);
        }

        $candidate->delete();
        return response()->json(['status' => true, 'message' => 'Candidate deleted.']);
    }

    // Download resume
    public function downloadResume($corp_id, $id)
    {
        $candidate = RecruitmentCandidate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        if (!$candidate->resume_path || !Storage::disk('public')->exists($candidate->resume_path)) {
            return response()->json(['status' => false, 'message' => 'Resume not found.'], 404);
        }

        return Storage::disk('public')->download($candidate->resume_path);
    }
}
