<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeProfilePhoto;
use App\Models\EmploymentDetail;

class EmployeeProfilePhotoApiController extends Controller
{
    // Add profile photo
    public function store(Request $request)
    {
        $request->validate([
            'emp_code' => 'required|string',
            'corp_id' => 'required|string',
            'photo' => 'required|file|max:5120' // 5MB = 5120KB
        ]);

        // Check if EmpCode exists in employment_details
        $exists = EmploymentDetail::where('EmpCode', $request->emp_code)
            ->where('corp_id', $request->corp_id)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => false,
                'message' => 'EmpCode not exists, please fill employment details first.'
            ], 400);
        }

        // Store file
        $path = $request->file('photo')->store('profile_photos', 'public');

        $photo = EmployeeProfilePhoto::create([
            'emp_code' => $request->emp_code,
            'corp_id' => $request->corp_id,
            'photo_url' => $path
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Profile photo uploaded successfully.',
            'data' => $photo
        ]);
    }

    // Update profile photo by corp_id and emp_code
    public function update(Request $request, $corp_id, $emp_code)
    {
        $request->validate([
            'photo' => 'required|file|max:5120' // 5MB
        ]);

        $photo = EmployeeProfilePhoto::where('corp_id', $corp_id)
            ->where('emp_code', $emp_code)
            ->first();

        if (!$photo) {
            return response()->json([
                'status' => false,
                'message' => 'Profile photo not found.'
            ], 404);
        }

        // Delete old file from storage
        \Storage::disk('public')->delete($photo->photo_url);

        // Store new file
        $path = $request->file('photo')->store('profile_photos', 'public');
        $photo->photo_url = $path;
        $photo->save();

        return response()->json([
            'status' => true,
            'message' => 'Profile photo updated successfully.',
            'data' => $photo
        ]);
    }

    // Fetch profile photo by corp_id and emp_code
    public function fetch($corp_id, $emp_code)
    {
        $photo = EmployeeProfilePhoto::where('corp_id', $corp_id)
            ->where('emp_code', $emp_code)
            ->first();

        if (!$photo) {
            return response()->json([
                'status' => false,
                'message' => 'Profile photo not found.',
                'data' => (object)[]
            ], 404);
        }

        // Generate download link
        $downloadUrl = url('storage/' . $photo->photo_url);

        return response()->json([
            'status' => true,
            'data' => [
                'emp_code' => $photo->emp_code,
                'corp_id' => $photo->corp_id,
                'photo_url' => $downloadUrl
            ]
        ]);
    }

    // Delete profile photo by corp_id and emp_code
    public function destroy($corp_id, $emp_code)
    {
        $photo = EmployeeProfilePhoto::where('corp_id', $corp_id)
            ->where('emp_code', $emp_code)
            ->first();

        if (!$photo) {
            return response()->json([
                'status' => false,
                'message' => 'Profile photo not found.'
            ], 404);
        }

        // Delete file from storage
        \Storage::disk('public')->delete($photo->photo_url);

        $photo->delete();

        return response()->json([
            'status' => true,
            'message' => 'Profile photo deleted successfully.'
        ]);
    }
}
