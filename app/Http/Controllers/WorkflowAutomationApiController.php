<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorkflowAutomation;
use Illuminate\Support\Facades\DB;

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

        // Fetch workflow_automation data
        $automationQuery = DB::table('workflow_automation')->select(
            'workflow_name',
            'description',
            'request_type',
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
                // Prepare row-specific filters
                $rowFilters = array_merge($filters, [
                    'workflow_name' => $item->workflow_name,
                    'request_type' => $item->request_type,
                ]);

                // Approvers for this automation row
                $approversQuery = DB::table('approvers')
                    ->select(
                        'workflow_name',
                        'request_type',
                        'approver',
                        'intimationYN',
                        'due_day',
                        'turnaround_time',
                        'active_yn'
                    );
                foreach ($rowFilters as $key => $val) {
                    $approversQuery->where($key, $val);
                }
                $approversArr = $approversQuery->get();

                // Conditional workflows for this automation row
                $condWorkflowsQuery = DB::table('conditional_workflows')
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
                    );
                foreach ($rowFilters as $key => $val) {
                    $condWorkflowsQuery->where($key, $val);
                }
                $condWorkflowsArr = $condWorkflowsQuery->get();

                $automationArr[] = [
                    'workflow_name' => $item->workflow_name,
                    'description' => $item->description,
                    'advance_applicability' => $item->advance_applicability,
                    'from_days' => $item->from_days,
                    'to_days' => $item->to_days,
                    'conditional_workflowYN' => $item->conditional_workflowYN,
                    'activeYN' => $item->activeYN,
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
}
