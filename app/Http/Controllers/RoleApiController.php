<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;

class RoleApiController extends Controller
{
    // Add Role (no duplicate for same corp_id)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'role_name' => 'required|string'
        ]);

        if (Role::where('corp_id', $request->corp_id)->where('role_name', $request->role_name)->exists()) {
            return response()->json([
                'message' => 'Role already exists for this corp_id, can\'t enter duplicate data'
            ], 409);
        }

        $role = Role::create([
            'corp_id' => $request->corp_id,
            'role_name' => $request->role_name
        ]);

        return response()->json(['message' => 'Role added', 'role' => $role], 201);
    }

    // Fetch roles by corp_id
    public function getByCorpId($corp_id)
    {
        $roles = Role::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $roles]);
    }

    // Delete role by corp_id and role_name
    public function destroy($corp_id, $role_name)
    {
        $role = Role::where('corp_id', $corp_id)->where('role_name', $role_name)->first();
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }
        $role->delete();
        return response()->json(['message' => 'Role deleted']);
    }
}
