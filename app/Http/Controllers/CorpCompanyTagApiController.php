<?php

namespace App\Http\Controllers;

use App\Models\CorpCompanyTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CorpCompanyTagApiController extends Controller
{
    public function index($corpId)
    {
        $tags = CorpCompanyTag::where('corp_id', $corpId)
            ->where('active_yn', 1)
            ->orderBy('company_tag')
            ->get()
            ->map(function ($tag) {
                return [
                    'corpId' => $tag->corp_id,
                    'companyTag' => $tag->company_tag,
                    'activeYn' => (int) $tag->active_yn,
                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Company tags retrieved successfully.',
            'data' => $tags,
        ]);
    }

    public function upsert(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string',
            'companyTag' => 'required|string',
            'oldCompanyTag' => 'nullable|string',
        ]);

        $corpId = $request->input('corpId');
        $companyTag = $request->input('companyTag');
        $oldCompanyTag = $request->input('oldCompanyTag');

        DB::beginTransaction();

        try {
            if ($oldCompanyTag && $oldCompanyTag !== $companyTag) {
                $existing = CorpCompanyTag::where('corp_id', $corpId)
                    ->where('company_tag', $oldCompanyTag)
                    ->first();

                if (!$existing) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'Old company tag not found for this corp.',
                    ], 404);
                }

                $conflict = CorpCompanyTag::where('corp_id', $corpId)
                    ->where('company_tag', $companyTag)
                    ->exists();

                if ($conflict) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'Target company tag already exists for this corp.',
                    ], 409);
                }

                $existing->update([
                    'company_tag' => $companyTag,
                    'active_yn' => 1,
                ]);

                $record = $existing->fresh();
                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'Company tag renamed successfully.',
                    'data' => [
                        'corpId' => $record->corp_id,
                        'companyTag' => $record->company_tag,
                        'activeYn' => (int) $record->active_yn,
                    ],
                ]);
            }

            $record = CorpCompanyTag::updateOrCreate(
                [
                    'corp_id' => $corpId,
                    'company_tag' => $companyTag,
                ],
                [
                    'active_yn' => 1,
                ]
            );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Company tag saved successfully.',
                'data' => [
                    'corpId' => $record->corp_id,
                    'companyTag' => $record->company_tag,
                    'activeYn' => (int) $record->active_yn,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Error saving company tag: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($corpId, $companyTag)
    {
        $tag = CorpCompanyTag::where('corp_id', $corpId)
            ->where('company_tag', $companyTag)
            ->first();

        if (!$tag) {
            return response()->json([
                'status' => false,
                'message' => 'Company tag not found.',
            ], 404);
        }

        $tag->update(['active_yn' => 0]);

        return response()->json([
            'status' => true,
            'message' => 'Company tag deactivated successfully.',
            'data' => [
                'corpId' => $tag->corp_id,
                'companyTag' => $tag->company_tag,
                'activeYn' => (int) $tag->active_yn,
            ],
        ]);
    }
}
