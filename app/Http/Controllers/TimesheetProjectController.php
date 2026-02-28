<?php

namespace App\Http\Controllers;

use App\Models\TsProject;
use App\Models\TsProjectAssignment;
use App\Models\TsProjectHistory;
use App\Models\UserLogin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimesheetProjectController extends Controller
{
    /**
     * List projects with role-based visibility.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = TsProject::with(['creator', 'members']);

        if ($user->isAdmin()) {
            // Admin sees all projects
        } elseif ($user->isSupervisor()) {
            $subordinateIds = $user->getVisibleSubordinateIds();
            $query->where(function ($q) use ($user, $subordinateIds) {
                $q->where('created_by', $user->user_login_id)
                  ->orWhereHas('members', function ($q2) use ($subordinateIds) {
                      $q2->whereIn('user_id', $subordinateIds);
                  });
            });
        } else {
            $query->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->user_login_id);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Create a new project. Admin/Supervisor only.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => ['nullable', Rule::in(['active', 'completed', 'on_hold', 'cancelled'])],
        ]);

        $project = TsProject::create([
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => $request->user()->user_login_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status ?? 'active',
        ]);

        TsProjectHistory::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->user_login_id,
            'action' => 'created',
            'new_value' => $project->name,
            'remarks' => 'Project created.',
        ]);

        $project->load(['creator', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully.',
            'data' => $project,
        ], 201);
    }

    /**
     * Show a single project.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $project = TsProject::with(['creator', 'members', 'tasks', 'histories.user'])
            ->findOrFail($id);

        if (!$this->canViewProject($user, $project)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    /**
     * Update a project. Admin/Supervisor only.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $project = TsProject::findOrFail($id);

        if (!$user->isAdmin() && $project->created_by !== $user->user_login_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the project creator or admin can update this project.',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'status' => ['sometimes', Rule::in(['active', 'completed', 'on_hold', 'cancelled'])],
        ]);

        $changes = [];
        foreach (['name', 'description', 'start_date', 'end_date', 'status'] as $field) {
            if ($request->has($field) && $project->{$field} != $request->{$field}) {
                $changes[$field] = ['old' => $project->{$field}, 'new' => $request->{$field}];
            }
        }

        $project->update($request->only(['name', 'description', 'start_date', 'end_date', 'status']));

        foreach ($changes as $field => $change) {
            TsProjectHistory::create([
                'project_id' => $project->id,
                'user_id' => $user->user_login_id,
                'action' => 'updated_' . $field,
                'old_value' => (string) $change['old'],
                'new_value' => (string) $change['new'],
                'remarks' => ucfirst(str_replace('_', ' ', $field)) . ' updated.',
            ]);
        }

        $project->load(['creator', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully.',
            'data' => $project,
        ]);
    }

    /**
     * Extend project timeline. Admin/Supervisor only.
     */
    public function extendTimeline(Request $request, $id)
    {
        $user = $request->user();
        $project = TsProject::findOrFail($id);

        if (!$user->isAdmin() && $project->created_by !== $user->user_login_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the project creator or admin can extend the timeline.',
            ], 403);
        }

        $request->validate([
            'extended_end_date' => 'required|date|after:' . ($project->extended_end_date ?? $project->end_date)->format('Y-m-d'),
            'reason' => 'required|string|max:500',
        ]);

        $oldDate = $project->extended_end_date ?? $project->end_date;
        $project->update(['extended_end_date' => $request->extended_end_date]);

        TsProjectHistory::create([
            'project_id' => $project->id,
            'user_id' => $user->user_login_id,
            'action' => 'timeline_extended',
            'old_value' => $oldDate->format('Y-m-d'),
            'new_value' => $request->extended_end_date,
            'remarks' => $request->reason,
        ]);

        $project->load(['creator', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Project timeline extended successfully.',
            'data' => $project,
        ]);
    }

    /**
     * Assign a member to a project. Admin/Supervisor only.
     */
    public function assignMember(Request $request, $id)
    {
        $user = $request->user();
        $project = TsProject::findOrFail($id);

        if (!$user->isAdmin() && $project->created_by !== $user->user_login_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the project creator or admin can assign members.',
            ], 403);
        }

        $request->validate([
            'member_user_id' => 'required|exists:userlogin,user_login_id',
        ]);

        $member = UserLogin::findOrFail($request->member_user_id);

        // Supervisor can only assign own team members
        if ($user->isSupervisor() && !$user->isMyTeamMember($member->user_login_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only assign your own team members.',
            ], 403);
        }

        if (!$member->isSubordinate()) {
            return response()->json([
                'success' => false,
                'message' => 'Only subordinates can be assigned to projects.',
            ], 422);
        }

        // Check if already assigned
        $exists = TsProjectAssignment::where('project_id', $id)
            ->where('user_id', $request->member_user_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'User is already assigned to this project.',
            ], 422);
        }

        TsProjectAssignment::create([
            'project_id' => $project->id,
            'user_id' => $request->member_user_id,
            'assigned_by' => $user->user_login_id,
        ]);

        TsProjectHistory::create([
            'project_id' => $project->id,
            'user_id' => $user->user_login_id,
            'action' => 'member_added',
            'new_value' => $member->username,
            'remarks' => "Assigned {$member->username} to project.",
        ]);

        $project->load(['creator', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Member assigned to project successfully.',
            'data' => $project,
        ]);
    }

    /**
     * Remove a member from a project. Admin/Supervisor only.
     */
    public function removeMember(Request $request, $id)
    {
        $user = $request->user();
        $project = TsProject::findOrFail($id);

        if (!$user->isAdmin() && $project->created_by !== $user->user_login_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the project creator or admin can remove members.',
            ], 403);
        }

        $request->validate([
            'member_user_id' => 'required|exists:userlogin,user_login_id',
        ]);

        $assignment = TsProjectAssignment::where('project_id', $id)
            ->where('user_id', $request->member_user_id)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'User is not assigned to this project.',
            ], 404);
        }

        $removedUser = UserLogin::find($request->member_user_id);
        $assignment->delete();

        TsProjectHistory::create([
            'project_id' => $project->id,
            'user_id' => $user->user_login_id,
            'action' => 'member_removed',
            'old_value' => $removedUser->username,
            'remarks' => "Removed {$removedUser->username} from project.",
        ]);

        $project->load(['creator', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Member removed from project successfully.',
            'data' => $project,
        ]);
    }

    /**
     * Delete a project. Admin only.
     */
    public function destroy(Request $request, $id)
    {
        $project = TsProject::findOrFail($id);
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully.',
        ]);
    }

    /**
     * Check if user can view the project.
     */
    private function canViewProject(UserLogin $user, TsProject $project): bool
    {
        if ($user->isAdmin()) return true;

        if ($user->isSupervisor()) {
            if ($project->created_by === $user->user_login_id) return true;
            $subordinateIds = $user->getVisibleSubordinateIds();
            return $project->members()->whereIn('user_id', $subordinateIds)->exists();
        }

        return $project->members()->where('user_id', $user->user_login_id)->exists();
    }
}
