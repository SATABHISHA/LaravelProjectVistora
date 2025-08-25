<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserLogin;
use Illuminate\Support\Facades\Hash;
use App\Models\CorporateId;

class UserLoginApiController extends Controller
{
    // Registration API
    public function register(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'email_id' => 'required|email',
            'username' => 'required|string',
            'password' => 'required|string|min:6',
            'empcode' => 'nullable|string',
            'company_name' => 'nullable|string', // Added validation for company_name
        ]);

        // Check if corp_id is active in corporateid table
        $corp = CorporateId::where('corp_id_name', $request->corp_id)->first();
        if (!$corp || (int)$corp->active_yn === 0) {
            return response()->json([
                'status' => false,
                'message' => 'Registration not allowed: Corporate ID is inactive.'
            ], 403);
        }

        // Check for duplicate email_id for the same corp_id
        $emailExists = UserLogin::where('corp_id', $request->corp_id)
            ->where('email_id', $request->email_id)
            ->exists();

        if ($emailExists) {
            return response()->json([
                'status' => false,
                'message' => 'This email ID is already registered for this corp_id.'
            ], 409);
        }

        // Check for duplicate empcode for the same corp_id
        if ($request->empcode) {
            $empcodeExists = UserLogin::where('corp_id', $request->corp_id)
                ->where('empcode', $request->empcode)
                ->exists();

            if ($empcodeExists) {
                return response()->json([
                    'status' => false,
                    'message' => 'This empcode is already registered for this corp_id.'
                ], 409);
            }
        }

        try {
            $user = UserLogin::create([
                'corp_id' => $request->corp_id,
                'email_id' => $request->email_id,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'empcode' => $request->empcode,
                'company_name' => $request->company_name, // Added company_name field
                'active_yn' => isset($request->active_yn) ? (int)$request->active_yn : 1, // Changed default to 1
                'admin_yn' => isset($request->admin_yn) ? (int)$request->admin_yn : 0,
                'supervisor_yn' => isset($request->supervisor_yn) ? (int)$request->supervisor_yn : 0,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
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

        if ((int)$user->active_yn !== 1) {
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
