<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmploymentDetail;
use App\Models\EmployeeDetail;
use App\Models\CompanyDetails;

class EmploymentDetailApiController extends Controller
{
    // Insert
    public function store(Request $request)
    {
        $data = $request->all();

        // Set ActiveYn to 1 if not provided
        if (!isset($data['ActiveYn'])) {
            $data['ActiveYn'] = 1;
        }

        // Check for duplicate EmpCode within the same corp_id
        $exists = EmploymentDetail::where('corp_id', $data['corp_id'])
            ->where('EmpCode', $data['EmpCode'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Duplicate entry: Employment detail with this EmpCode already exists for this corp_id.'
            ], 409);
        }

        $employment = EmploymentDetail::create($data);
        return response()->json(['data' => $employment], 201);
    }

    // Update by corp_id and EmpCode
    public function update(Request $request, $corp_id, $EmpCode)
    {
        $employment = EmploymentDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->firstOrFail();

        $employment->update($request->all());
        return response()->json(['data' => $employment]);
    }

    // Delete by corp_id and EmpCode
    public function destroy($corp_id, $EmpCode)
    {
        $employment = EmploymentDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->firstOrFail();

        $employment->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    // Fetch by corp_id and EmpCode
    public function show($corp_id, $EmpCode)
    {
        $employment = \App\Models\EmploymentDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->first();

        if (!$employment) {
            return response()->json([
                'status' => false,
                'message' => 'No employment record found for the given corp_id and EmpCode.',
                'data' => (object)[]
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Employment record found.',
            'data' => $employment
        ]);
    }

     // Fetch last EmpCode by corp_id and return incremented EmpCode, or EMP001 if none exists
    public function getNextEmpCode($corp_id)
    {
        $empCodes = EmploymentDetail::where('corp_id', $corp_id)
            ->pluck('EmpCode')
            ->toArray();

        $maxNumber = 0;
        $prefix = 'EMP';

        foreach ($empCodes as $code) {
            if (preg_match('/^([A-Za-z]*)(\d+)$/', $code, $matches)) {
                $prefix = $matches[1] ?: $prefix;
                $number = (int)$matches[2];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        if ($maxNumber === 0) {
            $nextEmpCode = $prefix . '001';
        } else {
            $nextEmpCode = $prefix . str_pad($maxNumber + 1, 3, '0', STR_PAD_LEFT);
        }

        return response()->json(['nextEmpCode' => $nextEmpCode]);
    }

    public function fetchEmploymentAndEmployeeDetails($corp_id, $company_name = null)
    {
        // Filter by corp_id and optionally by company_name
        $query = EmploymentDetail::where('corp_id', $corp_id);
        if ($company_name !== null) {
            $query->where('company_name', $company_name);
        }
        $employmentDetails = $query->get();

        $employeeDetailsCount = EmployeeDetail::where('corp_id', $corp_id)->count();

        $result = [];

        foreach ($employmentDetails as $employment) {
            $employee = EmployeeDetail::where('corp_id', $corp_id)
                ->where('EmpCode', $employment->EmpCode)
                ->first();

            $firstName = $employee && $employee->FirstName ? $employee->FirstName : '';
            $middleName = $employee && $employee->MiddleName ? $employee->MiddleName : '';
            $lastName = $employee && $employee->LastName ? $employee->LastName : '';

            if ($firstName === '' && $lastName === '') {
                $fullName = 'N/A';
            } else {
                $fullName = trim($firstName . ' ' . ($middleName !== '' ? $middleName . ' ' : '') . $lastName);
            }

            $row = [
                'EmpCode'            => $employment->EmpCode ?? 'N/A',
                'company_name'       => $employment->company_name ?? 'N/A',
                'Region'             => $employment->Region ?? 'N/A',
                'Branch'             => $employment->Branch ?? 'N/A',
                'SubBranch'          => $employment->SubBranch ?? 'N/A',
                'ReportingManager'   => $employment->ReportingManager ?? 'N/A',
                'FunctionalManager'  => $employment->FunctionalManager ?? 'N/A',
                'dateOfJoining'      => $employment->dateOfJoining ?? 'N/A',
                'WorkEmail'          => $employee && $employee->WorkEmail ? $employee->WorkEmail : 'N/A',
                'Mobile'             => $employee && $employee->Mobile ? $employee->Mobile : 'N/A',
                'FullName'           => $fullName,
            ];

            $result[] = $row;
        }

        // If both tables are empty, return message and empty data array
        if ($employmentDetails->isEmpty() && $employeeDetailsCount === 0) {
            return response()->json([
                'message' => 'No data',
                'data' => []
            ]);
        }

        // If no employment details, return one row with all N/A
        if (empty($result)) {
            $result[] = [
                'EmpCode'            => 'N/A',
                'company_name'       => 'N/A',
                'Region'             => 'N/A',
                'Branch'             => 'N/A',
                'SubBranch'          => 'N/A',
                'ReportingManager'   => 'N/A',
                'FunctionalManager'  => 'N/A',
                'dateOfJoining'      => 'N/A',
                'WorkEmail'          => 'N/A',
                'Mobile'             => 'N/A',
                'FullName'           => 'N/A',
            ];
        }

        return response()->json(['data' => $result]);
    }

    public function checkEmpCodeExists(Request $request)
    {
        $corp_id = $request->input('corp_id');
        $EmpCode = $request->input('EmpCode');

        $exists = EmploymentDetail::where('corp_id', $corp_id)
            ->where('EmpCode', $EmpCode)
            ->exists();

        return response()->json([
            'status' => $exists
        ]);
    }

    public function summaryByCorpId($corpid)
    {
        $totalEmployees = EmploymentDetail::where('corp_id', $corpid)->count();
        $totalActiveEmployees = EmploymentDetail::where('corp_id', $corpid)->where('ActiveYn', 1)->count();
        $totalInactiveEmployees = EmploymentDetail::where('corp_id', $corpid)->where('ActiveYn', 0)->count();

        // Get all unique company names for this corp_id
        $companyNames = \App\Models\CompanyDetails::where('corp_id', $corpid)->pluck('company_name')->unique();

        $companies = [];
        foreach ($companyNames as $companyName) {
            $total = \App\Models\EmploymentDetail::where('corp_id', $corpid)
                ->where('company_name', $companyName)
                ->count();
            $active = \App\Models\EmploymentDetail::where('corp_id', $corpid)
                ->where('company_name', $companyName)
                ->where('ActiveYn', 1)
                ->count();
            $inactive = \App\Models\EmploymentDetail::where('corp_id', $corpid)
                ->where('company_name', $companyName)
                ->where('ActiveYn', 0)
                ->count();
            $firstEmployment = \App\Models\EmploymentDetail::where('corp_id', $corpid)
                ->where('company_name', $companyName)
                ->first();

            // Generate a random light color hex code
            $colorcode = sprintf("#%02X%02X%02X", rand(180,255), rand(180,255), rand(180,255));
            // Generate a random dark color hex code
            $colorcode2 = sprintf("#%02X%02X%02X", rand(0,80), rand(0,80), rand(0,80));

            $companies[] = [
                'company_name' => $companyName,
                'total_employees' => $total,
                'total_active_employees' => $active,
                'total_inactive_employees' => $inactive,
                'corp_id' => $firstEmployment ? $firstEmployment->corp_id : $corpid,
                'id' => $firstEmployment ? $firstEmployment->id : null,
                'colorcode' => $colorcode,     // light color
                'colorcode2' => $colorcode2,   // dark color
            ];
        }

        return response()->json([
            'company_group' => $corpid,
            'total_employees' => $totalEmployees,
            'total_active_employees' => $totalActiveEmployees,
            'total_inactive_employees' => $totalInactiveEmployees,
            'companies' => $companies
        ]);
    }
}
