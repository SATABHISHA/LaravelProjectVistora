<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorkflowAutomation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowAutomationApiController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'puid' => 'required|string', // <-- Add this line
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

        $workflow = WorkflowAutomation::create($request->all());

        return response()->json([
            'message' => 'Workflow automation added',
            'workflow' => $workflow
        ], 201);
    }

    public function fetchAutomationData(Request $request)
    {
        // Collect filters
        $filters = [
            'corp_id' => $request->corp_id,
            'workflow_recruitment_yn' => $request->workflow_recruitment_yn,
            'workflow_workforce_yn' => $request->workflow_workforce_yn,
            'workflow_officetime_yn' => $request->workflow_officetime_yn,
            'workflow_payroll_yn' => $request->workflow_payroll_yn,
            'workflow_expense_yn' => $request->workflow_expense_yn,
            'workflow_performance_yn' => $request->workflow_performance_yn,
            'workflow_asset_yn' => $request->workflow_asset_yn,
        ];

        // Remove null filters
        $filters = array_filter($filters, function($v) { return $v !== null; });

        // Fetch workflow_automation data (add puid to select)
        $automationQuery = DB::table('workflow_automation')->select(
            'puid',
            'workflow_name',
            'description',
            'request_type',
            'flow_type',
            'applicability',
            'advance_applicability',
            'from_days',
            'to_days',
            'conditional_workflowYN',
            'activeYN'
        );
        foreach ($filters as $key => $val) {
            $automationQuery->where($key, $val);
        }
        $automationData = $automationQuery->get();

        // Group by request_type
        $grouped = $automationData->groupBy('request_type');
        $result = [];

        foreach ($grouped as $request_type => $automations) {
            $total_count = $automations->count();

            $automationArr = [];
            foreach ($automations as $item) {
                $puid = $item->puid;

                // Fetch approvers by puid
                $approversArr = DB::table('approvers')
                    ->where('puid', $puid)
                    ->select(
                        'workflow_name',
                        'request_type',
                        'approver',
                        'intimationYN',
                        'due_day',
                        'turnaround_time',
                        'active_yn'
                    )
                    ->get();

                // Get all approver names as comma-separated string
                $approverNames = $approversArr->pluck('approver')->unique()->implode(',');

                // Fetch conditional workflows by puid
                $condWorkflowsArr = DB::table('conditional_workflows')
                    ->where('puid', $puid)
                    ->select(
                        'workflow_name',
                        'request_type',
                        'condition_type',
                        'operation_type',
                        'value',
                        'role_name',
                        'intimationYn',
                        'due_day',
                        'turaround_time'
                    )
                    ->get();

                $automationArr[] = [
                    'puid' => $item->puid,
                    'workflow_name' => $item->workflow_name,
                    'description' => $item->description,
                    'flow_type' => $item->flow_type,
                    'applicability' => $item->applicability,
                    'advance_applicability' => $item->advance_applicability,
                    'from_days' => $item->from_days,
                    'to_days' => $item->to_days,
                    'conditional_workflowYN' => $item->conditional_workflowYN,
                    'activeYN' => $item->activeYN,
                    'approver' => $approverNames,
                    'approvers' => $approversArr,
                    'conditional_workflow' => $condWorkflowsArr,
                ];
            }

            $result[] = [
                'request_type' => $request_type,
                'total_count' => $total_count,
                'automation' => $automationArr,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Data fetched successfully.',
            'data' => $result
        ]);
    }

    public function generatePublicUid()
    {
        $publicUid = (string) Str::uuid(); // Generates a unique UUID v4 string

        return response()->json([
            'status' => true,
            'public_uid' => $publicUid
        ]);
    }

    public function deleteByPuid($puid)
    {
        // Delete from workflow_automation
        $workflowDeleted = DB::table('workflow_automation')->where('puid', $puid)->delete();

        // Delete from approvers
        $approversDeleted = DB::table('approvers')->where('puid', $puid)->delete();

        // Delete from conditional_workflows
        $conditionalDeleted = DB::table('conditional_workflows')->where('puid', $puid)->delete();

        $anyDeleted = $workflowDeleted || $approversDeleted || $conditionalDeleted;

        if ($anyDeleted) {
            return response()->json([
                'status' => true,
                'message' => 'Records deleted successfully for puid: ' . $puid
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No records found for puid: ' . $puid
            ], 404);
        }
    }

    public function updateByPuid(Request $request, $puid)
    {
        $workflow = WorkflowAutomation::where('puid', $puid)->first();

        if (!$workflow) {
            return response()->json([
                'status' => false,
                'message' => 'Workflow automation not found.'
            ], 404);
        }

        $request->validate([
            'corp_id' => 'sometimes|string',
            'workflow_name' => 'sometimes|string',
            'request_type' => 'sometimes|string',
            'workflow_recruitment_yn' => 'sometimes|integer',
            'workflow_workforce_yn' => 'sometimes|integer',
            'workflow_officetime_yn' => 'sometimes|integer',
            'workflow_payroll_yn' => 'sometimes|integer',
            'workflow_expense_yn' => 'sometimes|integer',
            'workflow_performance_yn' => 'sometimes|integer',
            'workflow_asset_yn' => 'sometimes|integer',
            'description' => 'nullable|string',
            'flow_type' => 'sometimes|string',
            'applicability' => 'sometimes|string',
            'advance_applicability' => 'sometimes|string',
            'from_days' => 'sometimes|string',
            'to_days' => 'sometimes|string',
            'conditional_workflowYN' => 'sometimes|integer',
            'activeYN' => 'sometimes|integer',
        ]);

        $workflow->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Workflow automation updated successfully.',
            'workflow' => $workflow
        ]);
    }
}
