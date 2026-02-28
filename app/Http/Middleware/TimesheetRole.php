<?php

namespace App\Http\Middleware;

use App\Models\UserLogin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimesheetRole
{
    /**
     * Handle an incoming request.
     * Resolves the user from the `user_id` query parameter (userlogin.user_login_id).
     * Usage: middleware('ts.role:admin,supervisor') or middleware('ts.role') for auth-only.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userId = $request->query('user_id') ?? $request->header('X-User-Id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'user_id query parameter is required (pass your user_login_id).',
            ], 401);
        }

        $user = UserLogin::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user_id. User not found.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
            ], 403);
        }

        // Check if user has the required role
        if (!empty($roles) && !in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Required role(s): ' . implode(', ', $roles),
            ], 403);
        }

        // Set the user on the request so controllers can use $request->user()
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
