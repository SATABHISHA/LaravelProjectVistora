<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentStageResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RecruitmentApplicationApiController extends Controller
{
    // -----------------------------------------------------------
    // APPLICATION CRUD
    // -----------------------------------------------------------

    // List all applications (optionally filter by corp_id + job_posting_id)
    public function index(Request $request, $corp_id)
    {
        $query = RecruitmentApplication::with(['candidate', 'jobPosting'])
            ->where('corp_id', $corp_id);

        if ($request->has('job_posting_id')) {
            $query->where('job_posting_id', $request->job_posting_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['status' => true, 'data' => $query->orderBy('created_at', 'desc')->get()]);
    }

    // Show single application with stage results
    public function show($corp_id, $id)
    {
        $application = RecruitmentApplication::with(['candidate', 'jobPosting', 'stageResults'])
            ->where('corp_id', $corp_id)
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json(['status' => false, 'message' => 'Application not found.'], 404);
        }

        return response()->json(['status' => true, 'data' => $application]);
    }

    // Create application
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id'         => 'required|string',
            'job_posting_id'  => 'required|integer|exists:recruitment_job_postings,id',
            'candidate_id'    => 'required|integer|exists:recruitment_candidates,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Prevent duplicate application for same candidate + job
        $exists = RecruitmentApplication::where('corp_id', $request->corp_id)
            ->where('job_posting_id', $request->job_posting_id)
            ->where('candidate_id', $request->candidate_id)
            ->exists();

        if ($exists) {
            return response()->json(['status' => false, 'message' => 'Candidate has already applied for this job.'], 409);
        }

        $data = $request->all();
        if (empty($data['applied_date'])) {
            $data['applied_date'] = now()->toDateString();
        }

        $application = RecruitmentApplication::create($data);
        return response()->json(['status' => true, 'message' => 'Application created.', 'data' => $application], 201);
    }

    // Update application (e.g., current stage, overall remarks)
    public function update(Request $request, $corp_id, $id)
    {
        $application = RecruitmentApplication::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $application->update($request->all());
        return response()->json(['status' => true, 'message' => 'Application updated.', 'data' => $application]);
    }

    // Delete application
    public function destroy($corp_id, $id)
    {
        $application = RecruitmentApplication::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $application->delete();
        return response()->json(['status' => true, 'message' => 'Application deleted.']);
    }

    // -----------------------------------------------------------
    // SELECTION / REJECTION
    // -----------------------------------------------------------

    // Select or reject a candidate
    public function decideCandidate(Request $request, $corp_id, $id)
    {
        $validator = Validator::make($request->all(), [
            'final_decision'  => 'required|in:Selected,Rejected',
            'decided_by'      => 'required|string',
            'overall_remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $application = RecruitmentApplication::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $application->update([
            'final_decision'  => $request->final_decision,
            'decided_by'      => $request->decided_by,
            'overall_remarks' => $request->overall_remarks,
            'decision_date'   => now(),
            'status'          => $request->final_decision === 'Selected' ? 'Selected' : 'Rejected',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Candidate ' . $request->final_decision . ' successfully.',
            'data'    => $application,
        ]);
    }

    // -----------------------------------------------------------
    // STAGE RESULTS / REMARKS
    // -----------------------------------------------------------

    // Add stage result (interview remarks) for an application
    public function addStageResult(Request $request, $corp_id, $application_id)
    {
        $validator = Validator::make($request->all(), [
            'stage_id'   => 'required|integer|exists:recruitment_stages,id',
            'outcome'    => 'nullable|in:Pass,Fail,On Hold,No Show',
            'rating'     => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Ensure application belongs to corp
        $application = RecruitmentApplication::where('corp_id', $corp_id)
            ->where('id', $application_id)
            ->firstOrFail();

        $data = $request->all();
        $data['corp_id']        = $corp_id;
        $data['application_id'] = $application_id;

        $result = RecruitmentStageResult::create($data);

        // Update the current_stage of the application
        if (!empty($request->stage_name)) {
            $application->update(['current_stage' => $request->stage_name]);
        }

        return response()->json(['status' => true, 'message' => 'Stage result added.', 'data' => $result], 201);
    }

    // Update a stage result
    public function updateStageResult(Request $request, $corp_id, $application_id, $result_id)
    {
        $result = RecruitmentStageResult::where('corp_id', $corp_id)
            ->where('application_id', $application_id)
            ->where('id', $result_id)
            ->firstOrFail();

        $result->update($request->all());
        return response()->json(['status' => true, 'message' => 'Stage result updated.', 'data' => $result]);
    }

    // List all stage results for an application
    public function listStageResults($corp_id, $application_id)
    {
        $results = RecruitmentStageResult::with('stage')
            ->where('corp_id', $corp_id)
            ->where('application_id', $application_id)
            ->orderBy('created_at')
            ->get();

        return response()->json(['status' => true, 'data' => $results]);
    }

    // Delete a stage result
    public function deleteStageResult($corp_id, $application_id, $result_id)
    {
        $result = RecruitmentStageResult::where('corp_id', $corp_id)
            ->where('application_id', $application_id)
            ->where('id', $result_id)
            ->firstOrFail();

        $result->delete();
        return response()->json(['status' => true, 'message' => 'Stage result deleted.']);
    }
}
