<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaygroupConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaygroupConfigurationApiController extends Controller
{
    // Add or Update (same GroupName can't be added for same corpId)
    public function storeOrUpdate(Request $request)
    {
        $data = $request->all();

        // Check for duplicate GroupName for the same corpId (excluding current puid if updating)
        $query = PaygroupConfiguration::where('corpId', $data['corpId'])
            ->where('GroupName', $data['GroupName']);
        if (isset($data['puid'])) {
            $query->where('puid', '!=', $data['puid']);
        }
        if ($query->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Duplicate GroupName for this corpId is not allowed.'
            ], 409);
        }

        // If puid exists, update; else, create
        $paygroup = PaygroupConfiguration::where('puid', $data['puid'] ?? '')->first();
        if ($paygroup) {
            $paygroup->update($data);
            $message = 'Paygroup configuration updated successfully.';
        } else {
            $paygroup = PaygroupConfiguration::create($data);
            $message = 'Paygroup configuration added successfully.';
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $paygroup
        ]);
    }

    // Fetch by corpId
    public function fetchByCorpId($corpId)
    {
        $data = PaygroupConfiguration::where('corpId', $corpId)->get();
        return response()->json(['data' => $data]);
    }

    // Fetch by puid
    public function fetchByPuid($puid)
    {
        $data = PaygroupConfiguration::where('puid', $puid)->first();
        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Paygroup configuration not found.',
                'data' => (object)[]
            ], 404);
        }
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // Delete by puid
    public function destroy($puid)
    {
        $deleted = PaygroupConfiguration::where('puid', $puid)->delete();
        if ($deleted) {
            return response()->json(['status' => true, 'message' => 'Deleted successfully']);
        } else {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }
    }

    // Fetch IncludedComponents as trimmed list by puid
    public function fetchIncludedComponents($puid)
    {
        $paygroup = PaygroupConfiguration::where('puid', $puid)->first();

        if (!$paygroup || empty($paygroup->IncludedComponents)) {
            return response()->json([
                'status' => false,
                'message' => 'No IncludedComponents found.',
                'data' => []
            ], 404);
        }

        // Split by comma, trim each value, and filter out empty strings
        $components = array_filter(array_map('trim', explode(',', $paygroup->IncludedComponents)));

        return response()->json([
            'status' => true,
            'data' => array_values($components)
        ]);
    }

    // Fetch GroupNames by Employment Details
    public function fetchGroupNamesByEmploymentDetails($corp_id, $EmpCode)
    {
        $employmentColumns = Schema::getColumnListing('employment_details');

        $paygroups = DB::table('paygroup_configurations')
            ->where('corpId', $corp_id)
            ->get();

        $employmentDetails = DB::table('employment_details')
            ->where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->first(); // Single record expected

        $result = [];

        foreach ($paygroups as $paygroup) {
            // Step 1: Prepare ApplicabiltyType columns (remove spaces, map 'Company' to 'company_name')
            $applicabilityTypesRaw = array_map('trim', explode(',', $paygroup->ApplicabiltyType ?? ''));
            $applicabilityTypes = [];
            foreach ($applicabilityTypesRaw as $type) {
                $type = str_replace(' ', '', $type);
                if (strtolower($type) === 'company') {
                    $type = 'company_name';
                }
                if ($type && in_array($type, $employmentColumns)) {
                    $applicabilityTypes[] = $type;
                }
            }

            // Step 2: Prepare ApplicableOn values (comma separated)
            $applicableOnValues = array_map('trim', explode(',', $paygroup->ApplicableOn ?? ''));

            // Step 3: Matching Logic
            $matched = false;

            foreach ($applicableOnValues as $val) {
                if ($val === '') continue;
                foreach ($applicabilityTypes as $col) {
                    if (isset($employmentDetails->$col)) {
                        $empValue = strtolower(trim($employmentDetails->$col));
                        if ($empValue === strtolower(trim($val))) {
                            $matched = true;
                            break 2; // Found a match, add GroupName
                        }
                    }
                }
            }

            if ($matched) {
                $result[] = $paygroup->GroupName;
            }
        }

        return response()->json([
            'status' => true,
            'data' => array_values(array_unique($result)),
        ]);
    }
}
