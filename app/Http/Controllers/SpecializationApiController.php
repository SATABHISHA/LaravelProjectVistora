<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Specialization;

class SpecializationApiController extends Controller
{
    // Add specialization
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'qualification_name' => 'required|string',
            'specialization_name' => 'required|string',
        ], [
            'corp_id.required' => 'corp_id field was blank',
            'qualification_name.required' => 'qualification_name field was blank',
            'specialization_name.required' => 'specialization_name field was blank',
        ]);

        $specialization = Specialization::create($request->all());

        return response()->json(['message' => 'Specialization added successfully', 'specialization' => $specialization], 201);
    }

    // Delete specialization by corp_id and id
    public function destroy($corp_id, $id)
    {
        $specialization = Specialization::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$specialization) {
            return response()->json(['message' => 'Specialization not found'], 404);
        }

        $specialization->delete();

        return response()->json(['message' => 'Specialization deleted successfully']);
    }

    // Fetch all qualifications by corp_id with specialization count
    public function getQualificationsWithSpecializationCount($corp_id)
    {
        $results = Specialization::where('corp_id', $corp_id)
            ->select('qualification_name')
            ->groupBy('qualification_name')
            ->get()
            ->map(function ($item) use ($corp_id) {
                $specializations = Specialization::where('corp_id', $corp_id)
                    ->where('qualification_name', $item->qualification_name)
                    ->get(['id', 'specialization_name']);

                return [
                    'qualification_name' => $item->qualification_name,
                    'specialization_count' => $specializations->count(),
                    'specializations' => $specializations->map(function ($spec) {
                        return [
                            'id' => $spec->id,
                            'specialization_name' => $spec->specialization_name,
                        ];
                    }),
                ];
            });

        return response()->json(['data' => $results]);
    }
}
