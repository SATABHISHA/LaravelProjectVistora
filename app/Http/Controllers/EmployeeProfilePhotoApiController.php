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
            'photo' => 'required|file|max:5120'
        ]);

        $exists = EmploymentDetail::where('EmpCode', $request->emp_code)
            ->where('corp_id', $request->corp_id)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => false,
                'message' => 'EmpCode not exists, please fill employment details first.'
            ], 400);
        }

        // Save file to public/profile_photos
        $file = $request->file('photo');
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('profile_photos'), $filename);

        $photo = EmployeeProfilePhoto::create([
            'emp_code' => $request->emp_code,
            'corp_id' => $request->corp_id,
            'photo_url' => $filename
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
            'photo' => 'required|file|max:5120'
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

        // Delete old file
        @unlink(public_path('profile_photos/' . $photo->photo_url));

        // Save new file
        $file = $request->file('photo');
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('profile_photos'), $filename);

        $photo->photo_url = $filename;
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

        $downloadUrl = url('profile_photos/' . $photo->photo_url);

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

        @unlink(public_path('profile_photos/' . $photo->photo_url));
        $photo->delete();

        return response()->json([
            'status' => true,
            'message' => 'Profile photo deleted successfully.'
        ]);
    }
}
