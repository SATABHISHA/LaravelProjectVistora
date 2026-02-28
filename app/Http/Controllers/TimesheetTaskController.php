<?php

namespace App\Http\Controllers;

use App\Models\TsTask;
use App\Models\TsTaskHistory;
use App\Models\TsUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimesheetTaskController extends Controller
{
    /**
     * List tasks with role-based visibility.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = TsTask::with([
            'project:id,name,status',
            'assignee:id,name,email,role',
            'assigner:id,name,email,role',
        ]);

        if ($user->isAdmin()) {
            // Admin sees all tasks
        } elseif ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            $query->where(function ($q) use ($visibleIds, $user) {
                $q->whereIn('assigned_to', $visibleIds)
                  ->orWhere('assigned_by', $user->id);
            });
        } else {
            // Subordinate sees only their own tasks
            $query->where('assigned_to', $user->id);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        $tasks = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Create/assign a task. Admin/Supervisor only.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'project_id' => 'nullable|exists:ts_projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'required|exists:ts_users,id',
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        $assignee = TsUser::findOrFail($request->assigned_to);

        if ($assignee->role !== 'subordinate') {
            return response()->json([
                'success' => false,
                'message' => 'Tasks can only be assigned to subordinates.',
            ], 422);
        }

        // Supervisor can only assign to own subordinates
        if ($user->isSupervisor() && $assignee->supervisor_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only assign tasks to your own subordinates.',
            ], 403);
        }

        $task = TsTask::create([
            'project_id' => $request->project_id,
            'title' => $request->title,
            'description' => $request->description,
            'assigned_to' => $request->assigned_to,
            'assigned_by' => $user->id,
            'priority' => $request->priority ?? 'medium',
            'due_date' => $request->due_date,
            'status' => 'pending',
        ]);

        TsTaskHistory::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'created',
            'new_value' => 'pending',
            'remarks' => "Task '{$task->title}' created and assigned to {$assignee->name}.",
        ]);

        $task->load(['project:id,name', 'assignee:id,name,email,role', 'assigner:id,name,email,role']);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'data' => $task,
        ], 201);
    }

    /**
     * Show a single task.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $task = TsTask::with([
            'project:id,name,status',
            'assignee:id,name,email,role',
            'assigner:id,name,email,role',
            'approver:id,name,email,role',
            'dailyReports',
            'histories.user:id,name',
        ])->findOrFail($id);

        if (!$this->canViewTask($user, $task)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    /**
     * Update task details. Admin/Supervisor only.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $task = TsTask::findOrFail($id);

        if (!$user->isAdmin() && $task->assigned_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the task creator or admin can update this task.',
            ], 403);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => 'nullable|date',
            'assigned_to' => 'sometimes|exists:ts_users,id',
        ]);

        $changes = [];
        foreach (['title', 'description', 'priority', 'due_date', 'assigned_to'] as $field) {
            if ($request->has($field) && $task->{$field} != $request->{$field}) {
                $changes[$field] = ['old' => $task->{$field}, 'new' => $request->{$field}];
            }
        }

        // If reassigning, validate the new assignee
        if ($request->has('assigned_to')) {
            $newAssignee = TsUser::findOrFail($request->assigned_to);
            if ($newAssignee->role !== 'subordinate') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tasks can only be assigned to subordinates.',
                ], 422);
            }
            if ($user->isSupervisor() && $newAssignee->supervisor_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only reassign tasks to your own subordinates.',
                ], 403);
            }
        }

        $task->update($request->only(['title', 'description', 'priority', 'due_date', 'assigned_to']));

        foreach ($changes as $field => $change) {
            TsTaskHistory::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'action' => $field === 'assigned_to' ? 'reassigned' : 'updated_' . $field,
                'old_value' => (string) $change['old'],
                'new_value' => (string) $change['new'],
                'remarks' => ucfirst(str_replace('_', ' ', $field)) . ' changed.',
            ]);
        }

        $task->load(['project:id,name', 'assignee:id,name,email,role', 'assigner:id,name,email,role']);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Update task status (subordinate marks task progress).
     */
    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        $task = TsTask::findOrFail($id);

        // Only the assignee can update status to in_progress or completed
        if ($task->assigned_to !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the assigned subordinate can update task status.',
            ], 403);
        }

        $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
            'remarks' => 'nullable|string',
        ]);

        $allowedTransitions = [
            'pending' => ['in_progress'],
            'in_progress' => ['completed'],
            'rejected' => ['in_progress'], // Allow re-work after rejection
        ];

        if (!isset($allowedTransitions[$task->status]) || !in_array($request->status, $allowedTransitions[$task->status])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot change status from '{$task->status}' to '{$request->status}'.",
            ], 422);
        }

        $oldStatus = $task->status;
        $task->status = $request->status;

        if ($request->status === 'completed') {
            $task->completed_at = now();
        }

        $task->save();

        TsTaskHistory::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'status_changed',
            'old_value' => $oldStatus,
            'new_value' => $request->status,
            'remarks' => $request->remarks,
        ]);

        $task->load(['project:id,name', 'assignee:id,name,email,role', 'assigner:id,name,email,role']);

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Approve a task. Admin/Supervisor only.
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $task = TsTask::findOrFail($id);

        if ($task->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed tasks can be approved.',
            ], 422);
        }

        // Supervisor can only approve tasks of own subordinates
        if ($user->isSupervisor()) {
            $assignee = TsUser::find($task->assigned_to);
            if ($assignee->supervisor_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only approve tasks of your own subordinates.',
                ], 403);
            }
        }

        $task->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        TsTaskHistory::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'approved',
            'old_value' => 'completed',
            'new_value' => 'approved',
            'remarks' => $request->remarks ?? 'Task approved.',
        ]);

        $task->load(['project:id,name', 'assignee:id,name,email,role', 'assigner:id,name,email,role', 'approver:id,name,email,role']);

        return response()->json([
            'success' => true,
            'message' => 'Task approved successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Reject a task. Admin/Supervisor only.
     */
    public function reject(Request $request, $id)
    {
        $user = $request->user();
        $task = TsTask::findOrFail($id);

        if ($task->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed tasks can be rejected.',
            ], 422);
        }

        // Supervisor can only reject tasks of own subordinates
        if ($user->isSupervisor()) {
            $assignee = TsUser::find($task->assigned_to);
            if ($assignee->supervisor_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only reject tasks of your own subordinates.',
                ], 403);
            }
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $task->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        TsTaskHistory::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'rejected',
            'old_value' => 'completed',
            'new_value' => 'rejected',
            'remarks' => $request->rejection_reason,
        ]);

        $task->load(['project:id,name', 'assignee:id,name,email,role', 'assigner:id,name,email,role']);

        return response()->json([
            'success' => true,
            'message' => 'Task rejected.',
            'data' => $task,
        ]);
    }

    /**
     * Delete a task. Admin only.
     */
    public function destroy(Request $request, $id)
    {
        $task = TsTask::findOrFail($id);
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.',
        ]);
    }

    private function canViewTask(TsUser $user, TsTask $task): bool
    {
        if ($user->isAdmin()) return true;

        if ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            return in_array($task->assigned_to, $visibleIds) || $task->assigned_by === $user->id;
        }

        return $task->assigned_to === $user->id;
    }
}
