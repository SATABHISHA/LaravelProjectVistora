<?php

namespace App\Http\Controllers;

use App\Models\TsUser;
use App\Models\UserLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TimesheetAuthController extends Controller
{
    /**
     * Register a new timesheet user.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:ts_users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => ['required', Rule::in(['admin', 'supervisor', 'subordinate'])],
            'supervisor_id' => 'nullable|exists:ts_users,id',
        ]);

        // If creating a subordinate, supervisor_id is required
        if ($request->role === 'subordinate' && !$request->supervisor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Subordinate must have a supervisor_id.',
            ], 422);
        }

        // Validate supervisor_id points to a supervisor or admin
        if ($request->supervisor_id) {
            $supervisor = TsUser::find($request->supervisor_id);
            if (!$supervisor || !in_array($supervisor->role, ['admin', 'supervisor'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'supervisor_id must reference a user with admin or supervisor role.',
                ], 422);
            }
        }

        $user = TsUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
            'supervisor_id' => $request->supervisor_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role', 'supervisor_id']),
            ],
        ], 201);
    }

    /**
     * Login - supports BOTH Vistora credentials and direct timesheet credentials.
     *
     * Vistora login: { "corp_id": "test", "email": "test@gmail.com", "password": "123456" }
     * Direct login:  { "email": "user@test.com", "password": "password123" }
     *
     * For Vistora login: authenticates against the `userlogin` table and
     * auto-creates/links a ts_user record on first login.
     */
    public function login(Request $request)
    {
        // Detect Vistora login when corp_id is provided
        if ($request->filled('corp_id')) {
            return $this->vistoraLogin($request);
        }

        return $this->directLogin($request);
    }

    /**
     * Vistora login: authenticate against the existing userlogin table.
     */
    private function vistoraLogin(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Find user in the Vistora userlogin table
        $vistoraUser = UserLogin::where('corp_id', $request->corp_id)
            ->where('email_id', $request->email)
            ->first();

        if (!$vistoraUser || !Hash::check($request->password, $vistoraUser->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Vistora credentials.',
            ], 401);
        }

        if ((int) $vistoraUser->active_yn !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Your Vistora account is inactive.',
            ], 403);
        }

        // Map Vistora role to timesheet role
        $role = $this->mapVistoraRole($vistoraUser);

        // Find or create linked ts_user
        $tsUser = TsUser::where('vistora_user_login_id', $vistoraUser->user_login_id)
            ->where('corp_id', $request->corp_id)
            ->first();

        if (!$tsUser) {
            // Also check by email
            $tsUser = TsUser::where('email', $request->email)
                ->where('corp_id', $request->corp_id)
                ->first();
        }

        if (!$tsUser) {
            // Auto-create timesheet user from Vistora credentials
            // Use plain-text password from request (TsUser 'hashed' cast will hash it)
            $tsUser = TsUser::create([
                'name' => $vistoraUser->username,
                'email' => $vistoraUser->email_id,
                'password' => $request->password,
                'role' => $role,
                'corp_id' => $request->corp_id,
                'vistora_user_login_id' => $vistoraUser->user_login_id,
                'is_active' => true,
            ]);
        } else {
            // Update link if not set
            if (!$tsUser->vistora_user_login_id) {
                $tsUser->update([
                    'vistora_user_login_id' => $vistoraUser->user_login_id,
                    'corp_id' => $request->corp_id,
                ]);
            }
            // Sync role from Vistora
            if ($tsUser->role !== $role) {
                $tsUser->update(['role' => $role]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful via Vistora.',
            'data' => [
                'user' => $tsUser->only(['id', 'name', 'email', 'role', 'supervisor_id', 'corp_id']),
            ],
        ]);
    }

    /**
     * Direct timesheet login (against ts_users table).
     */
    private function directLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = TsUser::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role', 'supervisor_id', 'corp_id']),
            ],
        ]);
    }

    /**
     * Map Vistora user roles to timesheet role.
     * admin_yn=1 → admin, supervisor_yn=1 → supervisor, otherwise → subordinate
     */
    private function mapVistoraRole(UserLogin $vistoraUser): string
    {
        if ((int) $vistoraUser->admin_yn === 1) {
            return 'admin';
        }
        if ((int) $vistoraUser->supervisor_yn === 1) {
            return 'supervisor';
        }
        return 'subordinate';
    }

    /**
     * Get user profile. Requires user_id query parameter.
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $user->load('supervisor:id,name,email,role');

        if ($user->isSupervisor() || $user->isAdmin()) {
            $user->load('subordinates:id,name,email,role,supervisor_id');
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * List users (Admin: all, Supervisor: own subordinates).
     */
    public function listUsers(Request $request)
    {
        $user = $request->user();
        $query = TsUser::query();

        if ($user->isSupervisor()) {
            $query->where(function ($q) use ($user) {
                $q->where('supervisor_id', $user->id)
                  ->orWhere('id', $user->id);
            });
        }
        // Admin sees all

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->select(['id', 'name', 'email', 'role', 'supervisor_id', 'is_active', 'corp_id', 'created_at'])
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }
}
