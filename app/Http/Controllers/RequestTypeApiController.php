<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RequestType;

class RequestTypeApiController extends Controller
{
    // Add request type (no duplicate for same corp_id)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'request_type_name' => 'required|string',
        ]);

        $exists = RequestType::where('corp_id', $request->corp_id)
            ->where('request_type_name', $request->request_type_name)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'This request type already exists for this corp_id.'
            ], 409);
        }

        $type = RequestType::create($request->only('corp_id', 'request_type_name'));

        return response()->json([
            'status' => true,
            'message' => 'Request type added successfully.',
            'data' => $type
        ], 201);
    }

    // Delete all request types by corp_id
    public function destroyByCorpId($corp_id)
    {
        $deleted = RequestType::where('corp_id', $corp_id)->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'All request types deleted for this corp_id.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No request types found for this corp_id.'
            ], 404);
        }
    }

    // Fetch all request types by corp_id
    public function getByCorpId($corp_id)
    {
        $types = RequestType::where('corp_id', $corp_id)->get();

        return response()->json([
            'status' => true,
            'data' => $types
        ]);
    }
}
