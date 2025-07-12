<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConditionalWorkflow;

class ConditionalWorkflowApiController extends Controller
{
    // Add
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
            'condition_type' => 'required|string',
            'operation_type' => 'required|string',
            'value' => 'required|string',
            'role_name' => 'required|string',
            'intimationYn' => 'required|integer',
            'due_day' => 'required|string',
            'turaround_time' => 'required|string',
        ]);

        $workflow = ConditionalWorkflow::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Conditional workflow added successfully.',
            'data' => $workflow
        ], 201);
    }

    // Delete by puid
    public function destroy($puid)
    {
        $workflow = ConditionalWorkflow::where('puid', $puid)->first();
        if (!$workflow) {
            return response()->json([
                'status' => false,
                'message' => 'Conditional workflow not found.'
            ], 404);
        }
        $workflow->delete();
        return response()->json([
            'status' => true,
            'message' => 'Conditional workflow deleted successfully.'
        ]);
    }

    // Update by puid
    public function update(Request $request, $puid)
    {
        $workflow = ConditionalWorkflow::where('puid', $puid)->first();
        if (!$workflow) {
            return response()->json([
                'status' => false,
                'message' => 'Conditional workflow not found.'
            ], 404);
        }

        $workflow->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Conditional workflow updated successfully.',
            'data' => $workflow
        ]);
    }

    // Fetch by filters, and parse role_name as array
    public function fetch(Request $request)
    {
        // If puid is provided, fetch only by puid and return a single record
        if ($request->puid) {
            $workflow = ConditionalWorkflow::where('puid', $request->puid)->first();

            if (!$workflow) {
                return response()->json([
                    'status' => false,
                    'message' => 'Conditional workflow not found.'
                ], 404);
            }

            // Parse role_name as array if comma separated
            $workflow['role_names'] = array_map('trim', explode(',', $workflow->role_name));

            return response()->json([
                'status' => true,
                'data' => $workflow
            ]);
        }

        // Otherwise, use filters as before
        $query = ConditionalWorkflow::query();

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

        $workflows = $query->get();

        // Parse role_name as array if comma separated
        $workflows = $workflows->map(function ($item) {
            $item['role_names'] = array_map('trim', explode(',', $item->role_name));
            return $item;
        });

        return response()->json([
            'status' => true,
            'data' => $workflows
        ]);
    }
}
