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
            ->first();

        if (!$employmentDetails) {
            return response()->json([
                'status' => false,
                'message' => 'Employment details not found.',
                'data' => []
            ], 404);
        }

        $result = [];

        foreach ($paygroups as $paygroup) {
            // Get field values directly
            $applicabilityTypeValue = $paygroup->ApplicabiltyType ?? $paygroup->ApplicabilityType ?? '';
            $advanceApplicabilityTypeValue = $paygroup->AdvanceApplicabilityType ?? '';
            $applicableOnValue = $paygroup->ApplicableOn ?? '';
            $advanceApplicableOnValue = $paygroup->AdvanceApplicableOn ?? '';

            // Process types and values
            $applicabilityTypes = $this->prepareTypes($applicabilityTypeValue, $employmentColumns);
            $advanceApplicabilityTypes = $this->prepareTypes($advanceApplicabilityTypeValue, $employmentColumns);
            $applicableOnValues = $this->prepareValues($applicableOnValue);
            $advanceApplicableOnValues = $this->prepareValues($advanceApplicableOnValue);

            // Check matches
            $applicableMatches = $this->checkMatch($applicabilityTypes, $applicableOnValues, $employmentDetails);
            $advanceApplicableMatches = $this->checkMatch($advanceApplicabilityTypes, $advanceApplicableOnValues, $employmentDetails);

            // Logic: if advance exists, both must match; if not, only applicable needs to match
            $hasAdvanceData = !empty(trim($advanceApplicableOnValue));
            
            if ($hasAdvanceData) {
                // Both ApplicableOn and AdvanceApplicableOn must match
                if ($applicableMatches && $advanceApplicableMatches) {
                    $result[] = $paygroup->GroupName;
                }
            } else {
                // Only ApplicableOn needs to match if AdvanceApplicableOn is null/empty
                if ($applicableMatches) {
                    $result[] = $paygroup->GroupName;
                }
            }
        }

        return response()->json([
            'status' => true,
            'data' => array_values(array_unique($result))
        ]);
    }

    private function prepareTypes($typeString, $employmentColumns)
    {
        $types = [];
        foreach (explode(',', $typeString ?? '') as $type) {
            $type = str_replace(' ', '', trim($type)); // Remove spaces but don't convert to lowercase
            
            // Handle specific mappings
            if (strtolower($type) === 'company') {
                $type = 'company_name';
            }
            
            if ($type && in_array($type, $employmentColumns)) {
                $types[] = $type;
            }
        }
        return $types;
    }

    private function prepareValues($valueString)
    {
        return array_filter(array_map(fn($v) => trim($v), explode(',', $valueString ?? ''))); // Keep original case and spaces
    }

    private function checkMatch($columns, $values, $employmentDetails)
    {
        if (empty($columns) || empty($values)) {
            return false;
        }
        
        // Check if at least one column-value pair matches
        foreach ($columns as $col) {
            if (isset($employmentDetails->$col)) {
                $empValue = trim($employmentDetails->$col); // Keep original case
                if (in_array($empValue, $values, true)) {
                    return true; // Found a match
                }
            }
        }
        return false; // No matches found
    }



}
