<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorkflowAutomation;

class WorkflowAutomationApiController extends Controller
{
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
            'description' => 'nullable|string',
            'flow_type' => 'required|string',
            'applicability' => 'required|string',
            'advance_applicability' => 'required|string',
            'from_days' => 'required|string',
            'to_days' => 'required|string',
            'conditional_workflowYN' => 'required|integer',
            'activeYN' => 'required|integer',
        ]);

        // Check for duplicate workflow_name for same request_type and corp_id
        if (WorkflowAutomation::where('corp_id', $request->corp_id)
            ->where('request_type', $request->request_type)
            ->where('workflow_name', $request->workflow_name)
            ->exists()) {
            return response()->json([
                'message' => 'Duplicate workflow_name for this request_type and corp_id'
            ], 409);
        }

        // No duplicate check for request_type anymore

        $workflow = WorkflowAutomation::create($request->all());

        return response()->json([
            'message' => 'Workflow automation added',
            'workflow' => $workflow
        ], 201);
    }
}
