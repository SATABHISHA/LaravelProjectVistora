<?php

namespace App\Http\Controllers;

use App\Models\UserLogin;
use App\Models\TsTeamMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TimesheetAuthController extends Controller
{
    /**
     * Login using Vistora credentials.
     * POST /api/timesheet/auth/login
     * Body: { "corp_id": "test", "email_id": "test@gmail.com", "password": "123456" }
     */
    public function login(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'email_id' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = UserLogin::where('corp_id', $request->corp_id)
            ->where('email_id', $request->email_id)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user->only([
                    'user_login_id', 'username', 'email_id', 'empcode',
                    'corp_id', 'company_name', 'role', 'is_active',
                ]),
            ],
        ]);
    }

    /**
     * Get user profile.
     * GET /api/timesheet/auth/profile?user_id=X
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        $data = $user->only([
            'user_login_id', 'username', 'email_id', 'empcode',
            'corp_id', 'company_name', 'role', 'is_active',
        ]);

        if ($user->isSupervisor() || $user->isAdmin()) {
            $data['team_members'] = $user->subordinates()->get();
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * List users (Admin: all in corp, Supervisor: own team members).
     * GET /api/timesheet/users?user_id=X
     */
    public function listUsers(Request $request)
    {
        $user = $request->user();
        $query = UserLogin::where('corp_id', $user->corp_id);

        if ($user->isSupervisor()) {
            $memberIds = TsTeamMember::where('supervisor_id', $user->user_login_id)
                ->pluck('member_id')->toArray();
            $memberIds[] = $user->user_login_id;
            $query->whereIn('user_login_id', $memberIds);
        }
        // Admin sees all users in corp

        if ($request->has('role')) {
            $role = $request->role;
            if ($role === 'admin') {
                $query->where('admin_yn', 1);
            } elseif ($role === 'supervisor') {
                $query->where('supervisor_yn', 1)->where('admin_yn', '!=', 1);
            } elseif ($role === 'subordinate') {
                $query->where('admin_yn', '!=', 1)->where('supervisor_yn', '!=', 1);
            }
        }

        $users = $query->select([
                'user_login_id', 'username', 'email_id', 'empcode',
                'corp_id', 'company_name', 'active_yn', 'admin_yn', 'supervisor_yn', 'created_at',
            ])
            ->orderBy('username')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Add a team member (supervisor-subordinate mapping).
     * POST /api/timesheet/team-members?user_id=X
     * Body: { "member_id": 5 }
     * Admin can also specify: { "member_id": 5, "supervisor_id": 3 }
     */
    public function addTeamMember(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'member_id' => 'required|integer',
        ]);

        $member = UserLogin::where('user_login_id', $request->member_id)->first();
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }

        // Admin can specify which supervisor; Supervisor assigns to themselves
        $supervisorId = $user->user_login_id;
        if ($user->isAdmin() && $request->filled('supervisor_id')) {
            $supervisorId = $request->supervisor_id;
        }

        // Check for duplicate
        $exists = TsTeamMember::where('supervisor_id', $supervisorId)
            ->where('member_id', $request->member_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a team member.',
            ], 422);
        }

        $teamMember = TsTeamMember::create([
            'supervisor_id' => $supervisorId,
            'member_id' => $request->member_id,
            'corp_id' => $user->corp_id,
        ]);

        $teamMember->load(['supervisor', 'member']);

        return response()->json([
            'success' => true,
            'message' => 'Team member added successfully.',
            'data' => $teamMember,
        ], 201);
    }

    /**
     * Remove a team member.
     * DELETE /api/timesheet/team-members?user_id=X
     * Body: { "member_id": 5 }
     */
    public function removeTeamMember(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'member_id' => 'required|integer',
        ]);

        $supervisorId = $user->user_login_id;
        if ($user->isAdmin() && $request->filled('supervisor_id')) {
            $supervisorId = $request->supervisor_id;
        }

        $teamMember = TsTeamMember::where('supervisor_id', $supervisorId)
            ->where('member_id', $request->member_id)
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member mapping not found.',
            ], 404);
        }

        $teamMember->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team member removed successfully.',
        ]);
    }

    /**
     * List team members for a supervisor.
     * GET /api/timesheet/team-members?user_id=X
     */
    public function listTeamMembers(Request $request)
    {
        $user = $request->user();

        $supervisorId = $user->user_login_id;
        if ($user->isAdmin() && $request->filled('supervisor_id')) {
            $supervisorId = $request->supervisor_id;
        }

        $members = TsTeamMember::where('supervisor_id', $supervisorId)
            ->with(['member', 'supervisor'])
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }
}
