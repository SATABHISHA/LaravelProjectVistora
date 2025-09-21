<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeePayrollSalaryProcess;

class EmployeePayrollSalaryProcessApiController extends Controller
{
    // Add or update Employee Payroll Salary Process
    public function storeOrUpdate(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string|max:10',
            'empCode' => 'required|string|max:20',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:2',
            'grossList' => 'required',
            'status' => 'required|string',
            'isShownToEmployeeYn' => 'required|integer',
        ]);

        $data = $request->all();

        $payroll = EmployeePayrollSalaryProcess::updateOrCreate(
            [
                'corpId' => $data['corpId'], 
                'empCode' => $data['empCode'],
                'year' => $data['year'],
                'month' => $data['month']
            ],
            $data
        );

        $status = $payroll->wasRecentlyCreated ? 'created' : 'updated';

        return response()->json([
            'message' => "Employee payroll salary process {$status} successfully",
            'status' => $status,
            'payroll' => $payroll
        ]);
    }

    // Fetch all by corpId
    public function getByCorpId($corpId)
    {
        $payrolls = EmployeePayrollSalaryProcess::where('corpId', $corpId)->get();
        return response()->json(['data' => $payrolls]);
    }

    // Fetch specific record
    public function getSpecific($corpId, $empCode, $year, $month)
    {
        $payroll = EmployeePayrollSalaryProcess::where('corpId', $corpId)
            ->where('empCode', $empCode)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if (!$payroll) {
            return response()->json(['message' => 'Payroll record not found'], 404);
        }

        return response()->json(['data' => $payroll]);
    }

    /**
     * Process employee salary structures into payroll entries in bulk
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkProcessFromSalaryStructures(Request $request)
    {
        // Validate required bulk fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'nullable|string|max:100', // Added optional companyName
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'status' => 'required|string',
            'isShownToEmployeeYn' => 'required|integer',
        ]);

        // Build query for salary structures
        $query = \App\Models\EmployeeSalaryStructure::where('corpId', $request->corpId);
        
        // Add company name filter if provided
        if ($request->has('companyName') && !empty($request->companyName)) {
            $query->where('companyName', $request->companyName);
        }
        
        // Get filtered salary structures
        $salaryStructures = $query->get();

        if ($salaryStructures->isEmpty()) {
            $message = $request->has('companyName') && !empty($request->companyName) 
                ? "No salary structures found for the given corpId and companyName" 
                : "No salary structures found for the given corpId";
                
            return response()->json([
                'message' => $message,
                'status' => 'error'
            ], 404);
        }

        $processedCount = 0;
        $errors = [];

        // Process each salary structure
        foreach ($salaryStructures as $structure) {
            try {
                // Create or update payroll entry
                \App\Models\EmployeePayrollSalaryProcess::updateOrCreate(
                    [
                        'corpId' => $structure->corpId,
                        'empCode' => $structure->empCode,
                        'year' => $request->year,
                        'month' => $request->month,
                    ],
                    [
                        'companyName' => $structure->companyName,
                        'grossList' => $structure->grossList,
                        'otherAllowances' => $structure->otherAlowances,
                        'otherBenefits' => $structure->otherBenifits,
                        'recurringDeduction' => $structure->recurringDeductions,
                        'status' => $request->status,
                        'isShownToEmployeeYn' => $request->isShownToEmployeeYn,
                    ]
                );

                $processedCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'empCode' => $structure->empCode,
                    'error' => $e->getMessage()
                ];
            }
        }

        $filterDescription = $request->has('companyName') && !empty($request->companyName)
            ? "corpId: {$request->corpId}, companyName: {$request->companyName}"
            : "corpId: {$request->corpId}";

        return response()->json([
            'message' => "Processed $processedCount employee records from salary structures to payroll",
            'filter' => $filterDescription,
            'total_structures' => $salaryStructures->count(),
            'processed' => $processedCount,
            'errors' => $errors,
            'status' => 'success'
        ]);
    }
}
