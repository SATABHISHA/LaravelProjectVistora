<?php

namespace App\Http\Controllers;

use App\Models\TsUser;
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
     * Login an existing timesheet user.
     */
    public function login(Request $request)
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
                'user' => $user->only(['id', 'name', 'email', 'role', 'supervisor_id']),
            ],
        ]);
    }

    /**
     * Get user profile. Requires user_id parameter.
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

        $users = $query->select(['id', 'name', 'email', 'role', 'supervisor_id', 'is_active', 'created_at'])
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }
}
