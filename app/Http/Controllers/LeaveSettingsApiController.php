<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LeaveSetting;

class LeaveSettingsApiController extends Controller
{
    /**
     * GET /leave-settings/{corpId}/{year}?company_name=...
     * Returns leave settings for a specific corp + company + year.
     */
    public function getByCorpYear(Request $request, $corpId, $year)
    {
        $request->validate([
            'company_name' => 'required|string'
        ]);

        $companyName = $request->query('company_name');

        $settings = LeaveSetting::where('corp_id', $corpId)
            ->where('company_name', $companyName)
            ->where('year', (int) $year)
            ->get();

        if ($settings->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No leave settings found for the given corp, company, and year.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Leave settings retrieved successfully.',
            'data' => $settings->map(fn ($s) => $this->formatSetting($s))
        ]);
    }

    /**
     * POST /leave-settings/upsert
     * Create or update leave settings for a company+year combination.
     */
    public function upsert(Request $request)
    {
        $request->validate([
            'corpId'                          => 'required|string',
            'companyName'                     => 'required|string',
            'year'                            => 'required|integer|min:2020|max:2100',
            'leaveSettings'                   => 'required|array|min:1',
            'leaveSettings.*.leaveType'       => 'required|string|in:Sick,Paid,Casual',
            'leaveSettings.*.monthlyAllocation' => 'required|numeric|min:0',
            'leaveSettings.*.yearlyAllocation'  => 'required|numeric|min:0',
            'leaveSettings.*.carryForwardLimit' => 'required|numeric|min:0',
            'leaveSettings.*.encashmentLimit'   => 'required|numeric|min:0',
        ]);

        $corpId      = $request->input('corpId');
        $companyName = $request->input('companyName');
        $year        = (int) $request->input('year');
        $leaveSettings = $request->input('leaveSettings');

        DB::beginTransaction();

        try {
            $upserted = [];

            foreach ($leaveSettings as $item) {
                $record = LeaveSetting::updateOrCreate(
                    [
                        'corp_id'      => $corpId,
                        'company_name' => $companyName,
                        'year'         => $year,
                        'leave_type'   => $item['leaveType'],
                    ],
                    [
                        'monthly_allocation'  => $item['monthlyAllocation'],
                        'yearly_allocation'   => $item['yearlyAllocation'],
                        'carry_forward_limit' => $item['carryForwardLimit'],
                        'encashment_limit'    => $item['encashmentLimit'],
                    ]
                );

                $upserted[] = $this->formatSetting($record);
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Leave settings saved successfully.',
                'data'    => $upserted
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Error saving leave settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normalize a LeaveSetting record to camelCase response keys.
     */
    private function formatSetting(LeaveSetting $setting): array
    {
        return [
            'leaveType'          => $setting->leave_type,
            'monthlyAllocation'  => number_format((float) $setting->monthly_allocation, 2, '.', ''),
            'yearlyAllocation'   => number_format((float) $setting->yearly_allocation, 2, '.', ''),
            'carryForwardLimit'  => number_format((float) $setting->carry_forward_limit, 2, '.', ''),
            'encashmentLimit'    => number_format((float) $setting->encashment_limit, 2, '.', ''),
            'companyName'        => $setting->company_name,
            'year'               => $setting->year,
        ];
    }
}
