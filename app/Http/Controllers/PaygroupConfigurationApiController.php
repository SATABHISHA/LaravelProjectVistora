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
    public function fetchGroupNamesByEmploymentDetails($EmpCode)
    {
        // Get all column names from employment_details
        $employmentColumns = Schema::getColumnListing('employment_details');

        // Get all paygroup_configurations
        $paygroups = DB::table('paygroup_configurations')->get();

        // Get employment_details filtered by EmpCode
        $employmentDetails = \DB::table('employment_details')
            ->where('EmpCode', $EmpCode)
            ->get();

        $result = [];

        foreach ($paygroups as $paygroup) {
            // Prepare ApplicabiltyType and AdvanceApplicabilityType columns
            $applicabilityTypes = array_map('trim', explode(',', $paygroup->ApplicabiltyType ?? ''));
            $advanceApplicabilityTypes = array_map('trim', explode(',', $paygroup->AdvanceApplicabilityType ?? ''));

            // Map 'Company' to 'company_name'
            $applicabilityTypes = array_map(function($type) {
                return strtolower($type) === 'company' ? 'company_name' : $type;
            }, $applicabilityTypes);

            $advanceApplicabilityTypes = array_map(function($type) {
                return strtolower($type) === 'company' ? 'company_name' : $type;
            }, $advanceApplicabilityTypes);

            // Filter only columns that exist in employment_details
            $applicabilityTypes = array_filter($applicabilityTypes, function($type) use ($employmentColumns) {
                return in_array($type, $employmentColumns);
            });

            $advanceApplicabilityTypes = array_filter($advanceApplicabilityTypes, function($type) use ($employmentColumns) {
                return in_array($type, $employmentColumns);
            });

            // Prepare ApplicableOn and AdvanceApplicableOn values (comma separated)
            $applicableOnValues = array_map('trim', explode(',', $paygroup->ApplicableOn ?? ''));
            $advanceApplicableOnValues = array_map('trim', explode(',', $paygroup->AdvanceApplicableOn ?? ''));

            // For each employment_details row, check if all applicable columns match the values
            foreach ($employmentDetails as $emp) {
                $match = true;

                // Check ApplicabiltyType columns and ApplicableOn values
                foreach ($applicabilityTypes as $idx => $col) {
                    if (isset($applicableOnValues[$idx]) && isset($emp->$col)) {
                        if ($emp->$col != $applicableOnValues[$idx]) {
                            $match = false;
                            break;
                        }
                    }
                }

                // Check AdvanceApplicabilityType columns and AdvanceApplicableOn values
                if ($match) {
                    foreach ($advanceApplicabilityTypes as $idx => $col) {
                        if (isset($advanceApplicableOnValues[$idx]) && isset($emp->$col)) {
                            if ($emp->$col != $advanceApplicableOnValues[$idx]) {
                                $match = false;
                                break;
                            }
                        }
                    }
                }

                if ($match) {
                    $result[] = $paygroup->GroupName;
                    break; // Only need to add once per paygroup
                }
            }
        }

        return response()->json([
            'status' => true,
            'data' => array_values(array_unique($result))
        ]);
    }
}
