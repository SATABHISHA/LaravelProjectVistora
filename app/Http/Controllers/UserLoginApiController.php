<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserLogin;
use Illuminate\Support\Facades\Hash;

class UserLoginApiController extends Controller
{
    // Registration API
    public function register(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'email_id' => 'required|email|unique:userlogin,email_id',
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $user = UserLogin::create([
            'corp_id' => $request->corp_id,
            'email_id' => $request->email_id,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'active_yn' => $request->active_yn ?? true,
            'admin_yn' => $request->admin_yn ?? false,
            'supervisor_yn' => $request->supervisor_yn ?? false,
        ]);

        return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);
    }

    // Login API
    public function login(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'email_id' => 'required|email',
            'password' => 'required|string',
        ]);

        // Null check for input values
        if (
            is_null($request->corp_id) ||
            is_null($request->email_id) ||
            is_null($request->password)
        ) {
            return response()->json([
                'status' => false,
                'message' => 'corp_id, email_id, and password fields are required'
            ], 400);
        }

        $user = UserLogin::where('corp_id', $request->corp_id)
            ->where('email_id', $request->email_id)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->active_yn) {
            return response()->json([
                'status' => false,
                'message' => 'User not active or Inactive user, please contact admin'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'user' => $user
        ]);
    }

    // Get all users
    public function index()
    {
        $users = UserLogin::all();
        return response()->json($users);
    }
}
