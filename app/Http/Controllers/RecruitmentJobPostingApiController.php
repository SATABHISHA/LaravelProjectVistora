<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentJobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RecruitmentJobPostingApiController extends Controller
{
    // List all job postings for a corp
    public function index($corp_id)
    {
        $postings = RecruitmentJobPosting::where('corp_id', $corp_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $postings]);
    }

    // Show single job posting
    public function show($corp_id, $id)
    {
        $posting = RecruitmentJobPosting::where('corp_id', $corp_id)
            ->where('id', $id)
            ->first();

        if (!$posting) {
            return response()->json(['status' => false, 'message' => 'Job posting not found.'], 404);
        }

        return response()->json(['status' => true, 'data' => $posting]);
    }

    // Create job posting
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id'    => 'required|string',
            'job_title'  => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $posting = RecruitmentJobPosting::create($request->all());
        return response()->json(['status' => true, 'message' => 'Job posting created.', 'data' => $posting], 201);
    }

    // Update job posting
    public function update(Request $request, $corp_id, $id)
    {
        $posting = RecruitmentJobPosting::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $posting->update($request->all());
        return response()->json(['status' => true, 'message' => 'Job posting updated.', 'data' => $posting]);
    }

    // Delete job posting
    public function destroy($corp_id, $id)
    {
        $posting = RecruitmentJobPosting::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $posting->delete();
        return response()->json(['status' => true, 'message' => 'Job posting deleted.']);
    }

    // Change status (Open / Closed / On Hold)
    public function changeStatus(Request $request, $corp_id, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Open,Closed,On Hold',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $posting = RecruitmentJobPosting::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $posting->update(['status' => $request->status]);
        return response()->json(['status' => true, 'message' => 'Status updated.', 'data' => $posting]);
    }
}
