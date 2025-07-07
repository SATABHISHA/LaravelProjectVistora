<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Workflow;

class WorkflowApiController extends Controller
{
    // Add workflow (no duplicate workflow for same corpid)
    public function store(Request $request)
    {
        $request->validate([
            'corpid' => 'required|string',
            'workflow' => 'required|string',
        ]);

        $exists = Workflow::where('corpid', $request->corpid)
            ->where('workflow', $request->workflow)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'This workflow already exists for this corpid.'
            ], 409);
        }

        $workflow = Workflow::create($request->only('corpid', 'workflow'));

        return response()->json([
            'status' => true,
            'message' => 'Workflow added successfully.',
            'data' => $workflow
        ], 201);
    }

    // Delete workflow by corpid and workflow
    public function destroy(Request $request)
    {
        $request->validate([
            'corpid' => 'required|string',
            'workflow' => 'required|string',
        ]);

        $deleted = Workflow::where('corpid', $request->corpid)
            ->where('workflow', $request->workflow)
            ->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Workflow deleted successfully.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Workflow not found for this corpid.'
            ], 404);
        }
    }

    // Delete all workflows by corpid
    public function destroyByCorpId($corpid)
    {
        $deleted = Workflow::where('corpid', $corpid)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'All workflows deleted for this corpid.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No workflows found for this corpid.'
            ], 404);
        }
    }

    // Fetch workflows by corpid
    public function getByCorpId($corpid)
    {
        $workflows = Workflow::where('corpid', $corpid)->get();

        return response()->json([
            'status' => true,
            'data' => $workflows
        ]);
    }
}
