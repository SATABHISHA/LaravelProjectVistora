<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserLogin;
use Illuminate\Support\Facades\Hash;
use App\Models\CorporateId;
use App\Models\EmployeeDetail;
use App\Models\EmploymentDetail;

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
            'company_name' => 'nullable|string',
            // Added validation for role fields
            'active_yn' => 'nullable|integer|in:0,1',
            'admin_yn' => 'nullable|integer|in:0,1',
            'supervisor_yn' => 'nullable|integer|in:0,1',
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
                'company_name' => $request->company_name,
                'active_yn' => $request->has('active_yn') ? (int)$request->active_yn : 1,
                'admin_yn' => $request->has('admin_yn') ? (int)$request->admin_yn : 0,
                'supervisor_yn' => $request->has('supervisor_yn') ? (int)$request->supervisor_yn : 0,
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

    /**
     * Update user details by corp_id and email_id.
     */
    public function update(Request $request, $corp_id, $email_id)
    {
        // Find the user to update
        $user = UserLogin::where('corp_id', $corp_id)
            ->where('email_id', $email_id)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Validate the incoming data
        $request->validate([
            'username' => 'sometimes|string',
            'password' => 'sometimes|string|min:6',
            'empcode' => 'sometimes|nullable|string',
            'company_name' => 'sometimes|nullable|string',
            'active_yn' => 'sometimes|integer|in:0,1',
            'admin_yn' => 'sometimes|integer|in:0,1',
            'supervisor_yn' => 'sometimes|integer|in:0,1',
        ]);

        // Check for empcode uniqueness if it's being changed
        if ($request->has('empcode') && $request->empcode !== $user->empcode) {
            $empcodeExists = UserLogin::where('corp_id', $corp_id)
                ->where('empcode', $request->empcode)
                ->exists();

            if ($empcodeExists) {
                return response()->json([
                    'status' => false,
                    'message' => 'This empcode is already taken by another user in this corporation.'
                ], 409);
            }
        }

        // Prepare the data for update
        $updateData = $request->only([
            'username', 'empcode', 'company_name', 'active_yn', 'admin_yn', 'supervisor_yn'
        ]);

        // Hash the password only if a new one is provided
        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        try {
            // Update the user with the prepared data
            $user->update($updateData);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Fetch a single user's details by corp_id and email_id.
     */
    public function show($corp_id, $email_id)
    {
        // Find the user by the composite key
        $user = UserLogin::where('corp_id', $corp_id)
            ->where('email_id', $email_id)
            ->first();

        // Check if the user was found
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Return the user details
        return response()->json([
            'status' => true,
            'user' => $user
        ]);
    }

    // Get all users
    public function index()
    {
        $users = UserLogin::all();
        return response()->json([
            'status' => true,
            'data' => $users
        ]);
    }

    /**
     * Check if essential user details (empcode, company_name, username) exist.
     */
    public function checkUserDetails($corp_id, $email_id)
    {
        // Find the user by corp_id and email_id
        $user = \App\Models\UserLogin::where('corp_id', $corp_id)
            ->where('email_id', $email_id)
            ->first();

        // Handle case where the user does not exist at all
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found for the given corp_id and email_id.'
            ], 404);
        }

        // Check for empty or null fields
        $missingFields = [];
        if (empty($user->empcode)) {
            $missingFields[] = 'empcode';
        }
        if (empty($user->company_name)) {
            $missingFields[] = 'company_name';
        }
        if (empty($user->username)) {
            $missingFields[] = 'username';
        }

        // If the missingFields array is not empty, it means some details are missing
        if (!empty($missingFields)) {
            return response()->json([
                'status' => false,
                'message' => 'User details are incomplete. Missing fields: ' . implode(', ', $missingFields)
            ]);
        }

        // If all checks pass, return a success response
        return response()->json([
            'status' => true,
            'message' => 'All required user details (empcode, company_name, username) are present.'
        ]);
    }

    /**
     * Get empcode with hyphen and full name by concatenating firstname, middlename, lastname
     * with optional company name filter
     */
    public function getEmpcodeWithFullName(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'company_name' => 'nullable|string',
        ]);

        $corp_id = $request->corp_id;
        $company_name = $request->company_name;

        // Build query to get employees with employment details
        $query = EmployeeDetail::select(
                'employee_details.EmpCode',
                'employee_details.FirstName',
                'employee_details.MiddleName',
                'employee_details.LastName',
                'employment_details.company_name'
            )
            ->leftJoin('employment_details', function($join) {
                $join->on('employee_details.corp_id', '=', 'employment_details.corp_id')
                     ->on('employee_details.EmpCode', '=', 'employment_details.EmpCode');
            })
            ->where('employee_details.corp_id', $corp_id);

        // Add company name filter if provided
        if ($company_name) {
            $query->where('employment_details.company_name', $company_name);
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            $message = $company_name 
                ? "No employees found for the given corp_id and company_name."
                : "No employees found for the given corp_id.";
            
            return response()->json([
                'status' => false,
                'message' => $message
            ], 404);
        }

        // Format the data with empcode-hyphen and concatenated full name
        $data = $employees->map(function ($employee) {
            // Concatenate names, handling null/empty values
            $nameParts = array_filter([
                $employee->FirstName,
                $employee->MiddleName,
                $employee->LastName
            ]);
            
            $fullName = implode(' ', $nameParts);
            
            // If no name parts, use 'N/A'
            if (empty($fullName)) {
                $fullName = 'N/A';
            }

            return [
                'empcode_with_hyphen' => $employee->EmpCode,
                'full_name' => $fullName,
                'formatted_display' => $employee->EmpCode . '- ' .$fullName,
                'company_name' => $employee->company_name ?? 'N/A'
            ];
        });

        $message = $company_name 
            ? "Employee data retrieved successfully for company: {$company_name}."
            : "Employee data retrieved successfully.";

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'filters_applied' => [
                'corp_id' => $corp_id,
                'company_name' => $company_name ?? 'All companies'
            ]
        ]);
    }
}
