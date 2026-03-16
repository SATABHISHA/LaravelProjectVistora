<?php

namespace App\Http\Controllers;

use App\Models\TsTask;
use App\Models\TsTaskHistory;
use App\Models\UserLogin;
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
        $query = TsTask::with(['project:id,name,status', 'assignee', 'assigner']);

        if ($user->isAdmin()) {
            // Admin sees all tasks in their corp
            $corpUserIds = $user->getVisibleUserIds();
            $query->where(function ($q) use ($corpUserIds) {
                $q->whereIn('assigned_to', $corpUserIds)
                  ->orWhereIn('assigned_by', $corpUserIds);
            });
        } elseif ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            $query->where(function ($q) use ($visibleIds, $user) {
                $q->whereIn('assigned_to', $visibleIds)
                  ->orWhere('assigned_by', $user->user_login_id);
            });
        } else {
            $query->where('assigned_to', $user->user_login_id);
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
            'assigned_to' => 'required|exists:userlogin,user_login_id',
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        $assignee = UserLogin::findOrFail($request->assigned_to);

        // Corp isolation: assignee must be in same corp
        if ($assignee->corp_id !== $user->corp_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in your organization.',
            ], 404);
        }

        if (!$assignee->isSubordinate()) {
            return response()->json([
                'success' => false,
                'message' => 'Tasks can only be assigned to subordinates.',
            ], 422);
        }

        // Supervisor can only assign to own team members
        if ($user->isSupervisor() && !$user->isMyTeamMember($assignee->user_login_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only assign tasks to your own team members.',
            ], 403);
        }

        $task = TsTask::create([
            'project_id' => $request->project_id,
            'title' => $request->title,
            'description' => $request->description,
            'assigned_to' => $request->assigned_to,
            'assigned_by' => $user->user_login_id,
            'priority' => $request->priority ?? 'medium',
            'due_date' => $request->due_date,
            'status' => 'pending',
        ]);

        TsTaskHistory::create([
            'task_id' => $task->id,
            'user_id' => $user->user_login_id,
            'action' => 'created',
            'new_value' => 'pending',
            'remarks' => "Task '{$task->title}' created and assigned to {$assignee->username}.",
        ]);

        $task->load(['project:id,name', 'assignee', 'assigner']);

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
            'assignee',
            'assigner',
            'approver',
            'dailyReports',
            'histories.user',
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
        $task = $this->findTaskInCorp($id, $user);

        if (!$user->isAdmin() && $task->assigned_by !== $user->user_login_id) {
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
            'assigned_to' => 'sometimes|exists:userlogin,user_login_id',
        ]);

        $changes = [];
        foreach (['title', 'description', 'priority', 'due_date', 'assigned_to'] as $field) {
            if ($request->has($field) && $task->{$field} != $request->{$field}) {
                $changes[$field] = ['old' => $task->{$field}, 'new' => $request->{$field}];
            }
        }

        // If reassigning, validate the new assignee
        if ($request->has('assigned_to')) {
            $newAssignee = UserLogin::findOrFail($request->assigned_to);
            if ($newAssignee->corp_id !== $user->corp_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found in your organization.',
                ], 404);
            }
            if (!$newAssignee->isSubordinate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tasks can only be assigned to subordinates.',
                ], 422);
            }
            if ($user->isSupervisor() && !$user->isMyTeamMember($newAssignee->user_login_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only reassign tasks to your own team members.',
                ], 403);
            }
        }

        $task->update($request->only(['title', 'description', 'priority', 'due_date', 'assigned_to']));

        foreach ($changes as $field => $change) {
            TsTaskHistory::create([
                'task_id' => $task->id,
                'user_id' => $user->user_login_id,
                'action' => $field === 'assigned_to' ? 'reassigned' : 'updated_' . $field,
                'old_value' => (string) $change['old'],
                'new_value' => (string) $change['new'],
                'remarks' => ucfirst(str_replace('_', ' ', $field)) . ' changed.',
            ]);
        }

        $task->load(['project:id,name', 'assignee', 'assigner']);

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
        $task = $this->findTaskInCorp($id, $user);

        if ($task->assigned_to !== $user->user_login_id) {
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
            'rejected' => ['in_progress'],
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
            'user_id' => $user->user_login_id,
            'action' => 'status_changed',
            'old_value' => $oldStatus,
            'new_value' => $request->status,
            'remarks' => $request->remarks,
        ]);

        $task->load(['project:id,name', 'assignee', 'assigner']);

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
        $task = $this->findTaskInCorp($id, $user);

        if ($task->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed tasks can be approved.',
            ], 422);
        }

        // Supervisor can only approve tasks of own team members
        if ($user->isSupervisor() && !$user->isMyTeamMember($task->assigned_to)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only approve tasks of your own team members.',
            ], 403);
        }

        $task->update([
            'status' => 'approved',
            'approved_by' => $user->user_login_id,
            'approved_at' => now(),
        ]);

        TsTaskHistory::create([
            'task_id' => $task->id,
            'user_id' => $user->user_login_id,
            'action' => 'approved',
            'old_value' => 'completed',
            'new_value' => 'approved',
            'remarks' => $request->remarks ?? 'Task approved.',
        ]);

        $task->load(['project:id,name', 'assignee', 'assigner', 'approver']);

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
        $task = $this->findTaskInCorp($id, $user);

        if ($task->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed tasks can be rejected.',
            ], 422);
        }

        // Supervisor can only reject tasks of own team members
        if ($user->isSupervisor() && !$user->isMyTeamMember($task->assigned_to)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only reject tasks of your own team members.',
            ], 403);
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
            'user_id' => $user->user_login_id,
            'action' => 'rejected',
            'old_value' => 'completed',
            'new_value' => 'rejected',
            'remarks' => $request->rejection_reason,
        ]);

        $task->load(['project:id,name', 'assignee', 'assigner']);

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
        $user = $request->user();
        $task = $this->findTaskInCorp($id, $user);
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.',
        ]);
    }

    private function canViewTask(UserLogin $user, TsTask $task): bool
    {
        // Corp isolation: ensure task belongs to user's corp
        $assigner = $task->relationLoaded('assigner') ? $task->assigner : UserLogin::find($task->assigned_by);
        if (!$assigner || $assigner->corp_id !== $user->corp_id) {
            return false;
        }

        if ($user->isAdmin()) return true;

        if ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            return in_array($task->assigned_to, $visibleIds) || $task->assigned_by === $user->user_login_id;
        }

        return $task->assigned_to === $user->user_login_id;
    }

    /**
     * Find a task scoped to the user's corp_id.
     */
    private function findTaskInCorp($id, UserLogin $user)
    {
        return TsTask::where('id', $id)
            ->whereHas('assigner', fn($q) => $q->where('corp_id', $user->corp_id))
            ->firstOrFail();
    }
}
