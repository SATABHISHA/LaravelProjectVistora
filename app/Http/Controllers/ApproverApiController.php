<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Approver;

class ApproverApiController extends Controller
{
    // Add Approver
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'workflow_name' => 'required|string',
            'request_type' => 'required|string',
            'workflow_recruitment_yn' => 'required|integer',
            'workflow_workforce_yn' => 'required|integer',
            'workflow_officetime_yn' => 'required|integer',
            'workflow_payroll_yn' => 'required|integer',
            'workflow_expense_yn' => 'required|integer',
            'workflow_performance_yn' => 'required|integer',
            'workflow_asset_yn' => 'required|integer',
            'approver' => 'required|string',
            'intimationYN' => 'required|integer',
            'due_day' => 'nullable|string',
            'turnaround_time' => 'nullable|string',
            'active_yn' => 'nullable|integer'
        ]);

        $approver = Approver::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Approver added successfully.',
            'data' => $approver
        ], 201);
    }

    // Delete Approver by id and corp_id
    public function destroy($corp_id, $id)
    {
        $approver = Approver::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$approver) {
            return response()->json([
                'status' => false,
                'message' => 'Approver not found.'
            ], 404);
        }
        $approver->delete();
        return response()->json([
            'status' => true,
            'message' => 'Approver deleted successfully.'
        ]);
    }

    // Fetch by filters
    public function fetch(Request $request)
    {
        $query = Approver::query();

        if ($request->corp_id) $query->where('corp_id', $request->corp_id);
        if ($request->workflow_name) $query->where('workflow_name', $request->workflow_name);
        if ($request->request_type) $query->where('request_type', $request->request_type);
        if ($request->workflow_recruitment_yn !== null) $query->where('workflow_recruitment_yn', $request->workflow_recruitment_yn);
        if ($request->workflow_workforce_yn !== null) $query->where('workflow_workforce_yn', $request->workflow_workforce_yn);
        if ($request->workflow_officetime_yn !== null) $query->where('workflow_officetime_yn', $request->workflow_officetime_yn);
        if ($request->workflow_payroll_yn !== null) $query->where('workflow_payroll_yn', $request->workflow_payroll_yn);
        if ($request->workflow_expense_yn !== null) $query->where('workflow_expense_yn', $request->workflow_expense_yn);
        if ($request->workflow_performance_yn !== null) $query->where('workflow_performance_yn', $request->workflow_performance_yn);
        if ($request->workflow_asset_yn !== null) $query->where('workflow_asset_yn', $request->workflow_asset_yn);

        $approvers = $query->get();

        return response()->json([
            'status' => true,
            'data' => $approvers
        ]);
    }
}
