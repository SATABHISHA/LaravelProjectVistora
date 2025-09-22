<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeePayrollSalaryProcess;
use App\Models\EmployeeDetail;
use App\Models\EmploymentDetail;
use App\Exports\PayrollExport;
use Maatwebsite\Excel\Facades\Excel;

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

    /**
     * Helper method to get full employee name by concatenating names
     */
    private function getFullEmployeeName($employee)
    {
        if (!$employee) {
            return 'N/A';
        }

        $firstName = $employee->FirstName ?? '';
        $middleName = $employee->MiddleName ?? '';
        $lastName = $employee->LastName ?? '';
        
        // Handle cases where all names might be empty
        if (empty($firstName) && empty($middleName) && empty($lastName)) {
            return 'N/A';
        }
        
        // Build full name with proper spacing
        $nameParts = array_filter([$firstName, $middleName, $lastName]);
        return implode(' ', $nameParts);
    }

    /**
     * Fetch all by corpId with optional company name filter
     * and include salary summary calculations for each record
     * 
     * @param string $corpId
     * @param string|null $companyName
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByCorpId($corpId, $companyName = null)
    {
        // Start building the query
        $query = EmployeePayrollSalaryProcess::where('corpId', $corpId);
        
        // Add company name filter if provided
        if ($companyName) {
            $query->where('companyName', $companyName);
        }
        
        $payrolls = $query->get();
        
        if ($payrolls->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No payroll records found',
                'data' => []
            ]);
        }

        // Format each payroll record
        $formattedPayrolls = $payrolls->map(function($payroll) {
            // Parse JSON data from fields
            $grossList = json_decode($payroll->grossList, true) ?: [];
            $otherAllowances = json_decode($payroll->otherAllowances, true) ?: [];
            $otherBenefits = json_decode($payroll->otherBenefits, true) ?: [];
            $recurringDeduction = json_decode($payroll->recurringDeduction, true) ?: [];

            // Calculate salary totals
            $salarySummary = $this->calculateSalaryTotals(
                $grossList,
                $otherBenefits, 
                $recurringDeduction,
                $otherAllowances
            );

            // Format individual payroll record
            return [
                'id' => $payroll->id,
                'corpId' => $payroll->corpId,
                'empCode' => $payroll->empCode,
                'companyName' => $payroll->companyName,
                'year' => $payroll->year,
                'month' => $payroll->month,
                'status' => $payroll->status,
                'isShownToEmployeeYn' => $payroll->isShownToEmployeeYn,
                'created_at' => $payroll->created_at,
                'updated_at' => $payroll->updated_at,
                'gross' => $grossList,
                'deductions' => $recurringDeduction,
                'otherBenefitsAllowances' => array_merge($otherBenefits, $otherAllowances),
                'summary' => [
                    'totalGross' => [
                        'monthly' => $salarySummary['monthlyGross'],
                        'annual' => $salarySummary['annualGross']
                    ],
                    'totalDeductions' => [
                        'monthly' => $salarySummary['monthlyDeduction'],
                        'annual' => $salarySummary['annualDeduction']
                    ],
                    'totalBenefits' => [
                        'monthly' => $salarySummary['monthlyAllowance'],
                        'annual' => $salarySummary['annualAllowance']
                    ],
                    'netSalary' => [
                        'monthly' => $salarySummary['monthlyNetSalary'],
                        'annual' => $salarySummary['annualNetSalary']
                    ]
                ]
            ];
        });
        
        return response()->json([
            'status' => true,
            'data' => $formattedPayrolls
        ]);
    }

    /**
     * Fetch specific record with optional company name filter and calculate salary summary
     * 
     * @param string $corpId
     * @param string $empCode
     * @param string $year
     * @param string $month
     * @param string|null $companyName
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSpecific($corpId, $empCode, $year, $month, $companyName = null)
    {
        // Start building the query
        $query = EmployeePayrollSalaryProcess::where('corpId', $corpId)
            ->where('empCode', $empCode)
            ->where('year', $year)
            ->where('month', $month);
        
        // Add company name filter if provided
        if ($companyName) {
            $query->where('companyName', $companyName);
        }
        
        // Get the result
        $payroll = $query->first();

        if (!$payroll) {
            return response()->json([
                'status' => false,
                'message' => 'Payroll record not found', 
                'filters' => [
                    'corpId' => $corpId,
                    'empCode' => $empCode,
                    'year' => $year,
                    'month' => $month,
                    'companyName' => $companyName ?: 'Not specified'
                ]
            ], 404);
        }

        // Parse JSON data from fields
        $grossList = json_decode($payroll->grossList, true) ?: [];
        $otherAllowances = json_decode($payroll->otherAllowances, true) ?: [];
        $otherBenefits = json_decode($payroll->otherBenefits, true) ?: [];
        $recurringDeduction = json_decode($payroll->recurringDeduction, true) ?: [];

        // Calculate salary totals
        $salarySummary = $this->calculateSalaryTotals(
            $grossList,
            $otherBenefits, 
            $recurringDeduction,
            $otherAllowances
        );

        // Format response in the desired structure
        $response = [
            'status' => true,
            'data' => [
                'id' => $payroll->id,
                'corpId' => $payroll->corpId,
                'empCode' => $payroll->empCode,
                'companyName' => $payroll->companyName,
                'year' => $payroll->year,
                'month' => $payroll->month,
                'status' => $payroll->status,
                'isShownToEmployeeYn' => $payroll->isShownToEmployeeYn,
                'created_at' => $payroll->created_at,
                'updated_at' => $payroll->updated_at,
                'gross' => $grossList,
                'deductions' => $recurringDeduction,
                'otherBenefitsAllowances' => array_merge($otherBenefits, $otherAllowances),
                'summary' => [
                    'totalGross' => [
                        'monthly' => $salarySummary['monthlyGross'],
                        'annual' => $salarySummary['annualGross']
                    ],
                    'totalDeductions' => [
                        'monthly' => $salarySummary['monthlyDeduction'],
                        'annual' => $salarySummary['annualDeduction']
                    ],
                    'totalBenefits' => [
                        'monthly' => $salarySummary['monthlyAllowance'],
                        'annual' => $salarySummary['annualAllowance']
                    ],
                    'netSalary' => [
                        'monthly' => $salarySummary['monthlyNetSalary'],
                        'annual' => $salarySummary['annualNetSalary']
                    ]
                ]
            ]
        ];

        return response()->json($response);
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
            'companyName' => 'nullable|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'status' => 'required|string',
            'isShownToEmployeeYn' => 'required|integer',
        ]);

        // Check if payroll already exists for this period
        $existingPayrollQuery = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
            ->where('year', $request->year)
            ->where('month', $request->month);

        // Add company name filter if provided
        if ($request->has('companyName') && !empty($request->companyName)) {
            $existingPayrollQuery->where('companyName', $request->companyName);
        }

        $existingPayroll = $existingPayrollQuery->first();

        if ($existingPayroll) {
            $filterDescription = $request->has('companyName') && !empty($request->companyName)
                ? "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}"
                : "corpId: {$request->corpId}, year: {$request->year}, month: {$request->month}";

            return response()->json([
                'status' => false,
                'message' => 'Payroll for this period has already been processed',
                'filter' => $filterDescription,
                'existing_records_count' => $existingPayrollQuery->count(),
                'duplicate_prevention' => true
            ], 409); // 409 Conflict status code
        }

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
                'status' => false,
                'message' => $message
            ], 404);
        }

        $processedCount = 0;
        $errors = [];

        // Process each salary structure
        foreach ($salaryStructures as $structure) {
            try {
                // Create payroll entry (using create instead of updateOrCreate to prevent duplicates)
                EmployeePayrollSalaryProcess::create([
                    'corpId' => $structure->corpId,
                    'empCode' => $structure->empCode,
                    'companyName' => $structure->companyName,
                    'year' => $request->year,
                    'month' => $request->month,
                    'grossList' => $structure->grossList,
                    'otherAllowances' => $structure->otherAlowances,
                    'otherBenefits' => $structure->otherBenifits,
                    'recurringDeduction' => $structure->recurringDeductions,
                    'status' => $request->status,
                    'isShownToEmployeeYn' => $request->isShownToEmployeeYn,
                ]);

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
            'status' => true,
            'message' => "Successfully processed $processedCount employee records from salary structures to payroll",
            'filter' => $filterDescription,
            'total_structures' => $salaryStructures->count(),
            'processed' => $processedCount,
            'errors' => $errors
        ]);
    }

    /**
     * Export payroll data to Excel for all employees
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportPayrollExcel(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
        ]);

        try {
            // Get payroll records with employee and employment details
            $payrollRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $request->month)
                ->with(['employeeDetail', 'employmentDetail'])
                ->get();

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No payroll records found for the specified period');
            }

            $excelData = [];
            $dynamicHeaders = [];

            foreach ($payrollRecords as $record) {
                // Get employee details
                $employeeDetail = $record->employeeDetail;
                $employmentDetail = $record->employmentDetail;

                // Build full name
                $fullName = '';
                if ($employeeDetail) {
                    $firstName = $employeeDetail->FirstName ?? '';
                    $middleName = $employeeDetail->MiddleName ?? '';
                    $lastName = $employeeDetail->LastName ?? '';
                    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                }

                // Get designation and date of joining
                $designation = $employmentDetail->designation ?? '';
                $dateOfJoining = $employmentDetail->dateofJoining ?? '';

                // Parse JSON fields
                $grossList = json_decode($record->grossList, true) ?: [];
                $otherBenefits = json_decode($record->otherBenefits, true) ?: [];
                $recurringDeductions = json_decode($record->recurringDeduction, true) ?: [];

                // Collect all dynamic keys for headers
                $allKeys = array_merge(
                    array_keys($grossList),
                    array_keys($otherBenefits),
                    array_keys($recurringDeductions)
                );
                $dynamicHeaders = array_unique(array_merge($dynamicHeaders, $allKeys));

                // Calculate totals
                $grossSalary = 0;
                $grossDeduction = 0;

                // Sum calculated values from grossList
                foreach ($grossList as $item) {
                    if (isset($item['calculatedValue'])) {
                        $grossSalary += (float)$item['calculatedValue'];
                    }
                }

                // Sum calculated values from otherBenefits
                foreach ($otherBenefits as $item) {
                    if (isset($item['calculatedValue'])) {
                        $grossSalary += (float)$item['calculatedValue'];
                    }
                }

                // Sum calculated values from recurringDeductions
                foreach ($recurringDeductions as $item) {
                    if (isset($item['calculatedValue'])) {
                        $grossDeduction += (float)$item['calculatedValue'];
                    }
                }

                $netTakeHome = $grossSalary - $grossDeduction;

                // Build row data
                $row = [
                    $record->empCode,
                    $fullName,
                    $designation,
                    $dateOfJoining,
                    round($grossSalary, 2),
                    round($grossDeduction, 2),
                    round($netTakeHome, 2)
                ];

                // Add dynamic column values
                foreach ($dynamicHeaders as $key) {
                    $value = '';
                    if (isset($grossList[$key]['calculatedValue'])) {
                        $value = $grossList[$key]['calculatedValue'];
                    } elseif (isset($otherBenefits[$key]['calculatedValue'])) {
                        $value = $otherBenefits[$key]['calculatedValue'];
                    } elseif (isset($recurringDeductions[$key]['calculatedValue'])) {
                        $value = $recurringDeductions[$key]['calculatedValue'];
                    }
                    $row[] = $value;
                }

                $excelData[] = $row;
            }

            // Generate filename
            $fileName = "Payroll_{$request->companyName}_{$request->year}_{$request->month}.xlsx";

            return Excel::download(new PayrollExport($excelData, $request->companyName, $request->month, $request->year, $dynamicHeaders), $fileName);

        } catch (\Exception $e) {
            abort(500, 'Error in exporting payroll data: ' . $e->getMessage());
        }
    }

    /**
     * Calculate salary totals
     *
     * @param array $grossList
     * @param array $otherBenefits
     * @param array $recurringDeductions
     * @param array $otherAllowances
     * @return array
     */
    private function calculateSalaryTotals($grossList, $otherBenefits, $recurringDeductions, $otherAllowances = [])
    {
        // Initialize totals
        $monthlyGross = 0.0;
        $monthlyAllowance = 0.0;
        $monthlyDeduction = 0.0;

        // Calculate gross total
        if (is_array($grossList)) {
            foreach ($grossList as $item) {
                if (isset($item['calculatedValue'])) {
                    $monthlyGross += (float)$item['calculatedValue'];
                }
            }
        }

        // Calculate other benefits total
        if (is_array($otherBenefits)) {
            foreach ($otherBenefits as $item) {
                if (isset($item['calculatedValue'])) {
                    $monthlyAllowance += (float)$item['calculatedValue'];
                }
            }
        }

        // Calculate other allowances total (if provided separately)
        if (is_array($otherAllowances)) {
            foreach ($otherAllowances as $item) {
                if (isset($item['calculatedValue'])) {
                    $monthlyAllowance += (float)$item['calculatedValue'];
                }
            }
        }

        // Calculate deductions total
        if (is_array($recurringDeductions)) {
            foreach ($recurringDeductions as $item) {
                if (isset($item['calculatedValue'])) {
                    $monthlyDeduction += (float)$item['calculatedValue'];
                }
            }
        }

        // Calculate net salary
        $monthlyNetSalary = $monthlyGross + $monthlyAllowance - $monthlyDeduction;

        // Calculate annual values
        $annualGross = $monthlyGross * 12;
        $annualAllowance = $monthlyAllowance * 12;
        $annualDeduction = $monthlyDeduction * 12;
        $annualNetSalary = $monthlyNetSalary * 12;

        return [
            'monthlyGross' => round($monthlyGross, 2),
            'annualGross' => round($annualGross, 2),
            'monthlyDeduction' => round($monthlyDeduction, 2),
            'annualDeduction' => round($annualDeduction, 2),
            'monthlyAllowance' => round($monthlyAllowance, 2),
            'annualAllowance' => round($annualAllowance, 2),
            'monthlyNetSalary' => round($monthlyNetSalary, 2),
            'annualNetSalary' => round($annualNetSalary, 2)
        ];
    }

    /**
     * Safely sum values in an array, recursively processing nested arrays
     *
     * @param array $array The array to sum
     * @return float The sum of numeric values
     */
    private function safeArraySum($array) 
    {
        $sum = 0;
        
        if (!is_array($array)) {
            return 0;
        }
        
        foreach ($array as $value) {
            if (is_array($value)) {
                // Recursively sum nested arrays
                $sum += $this->safeArraySum($value);
            } else if (is_numeric($value)) {
                // Only add numeric values
                $sum += $value;
            }
        }
        
        return $sum;
    }

    /**
     * Check if payroll has been initiated for a specific period
     *
     * @param string $corpId
     * @param string $companyName
     * @param string $year
     * @param string $month
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPayrollInitiated($corpId, $companyName, $year, $month)
    {
        try {
            // Check if payroll exists for this period
            $payrollExists = EmployeePayrollSalaryProcess::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('year', $year)
                ->where('month', $month)
                ->exists();

            $filterDescription = "corpId: {$corpId}, companyName: {$companyName}, year: {$year}, month: {$month}";

            if ($payrollExists) {
                // Get count of records for additional info
                $recordsCount = EmployeePayrollSalaryProcess::where('corpId', $corpId)
                    ->where('companyName', $companyName)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->count();

                return response()->json([
                    'status' => true,
                    'initiated' => true,
                    'message' => 'Payroll has been initiated for this period',
                    'filter' => $filterDescription,
                    'records_count' => $recordsCount
                ]);
            } else {
                return response()->json([
                    'status' => true,
                    'initiated' => false,
                    'message' => 'Payroll has not been initiated for this period',
                    'filter' => $filterDescription,
                    'records_count' => 0
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'initiated' => false,
                'message' => 'Error checking payroll status: ' . $e->getMessage(),
                'filter' => "corpId: {$corpId}, companyName: {$companyName}, year: {$year}, month: {$month}"
            ], 500);
        }
    }

    /**
     * Release salary in bulk - update status from any status to 'Released'
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function releaseSalary(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
        ]);

        try {
            // Check if payroll entries exist for this period
            $payrollQuery = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $request->month);

            $payrollRecords = $payrollQuery->get();

            if ($payrollRecords->isEmpty()) {
                $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}";
                
                return response()->json([
                    'status' => false,
                    'message' => 'No payroll entries found for the specified period',
                    'filter' => $filterDescription,
                    'records_found' => 0
                ], 404);
            }

            // Get current status breakdown before update
            $statusBreakdown = $payrollRecords->groupBy('status')->map(function ($group) {
                return $group->count();
            })->toArray();

            // Count records that are already released
            $alreadyReleasedCount = $payrollRecords->where('status', 'Released')->count();
            $toBeUpdatedCount = $payrollRecords->where('status', '!=', 'Released')->count();

            // Update all records to 'Released' status
            $updatedCount = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $request->month)
                ->where('status', '!=', 'Released') // Only update non-released records
                ->update([
                    'status' => 'Released',
                    'updated_at' => now()
                ]);

            $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}";

            // Prepare response message
            if ($updatedCount > 0) {
                $message = "Successfully released salary for {$updatedCount} employees";
                if ($alreadyReleasedCount > 0) {
                    $message .= ". {$alreadyReleasedCount} employees were already in 'Released' status";
                }
            } else {
                $message = "All {$payrollRecords->count()} employees are already in 'Released' status";
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'filter' => $filterDescription,
                'summary' => [
                    'total_records' => $payrollRecords->count(),
                    'updated_to_released' => $updatedCount,
                    'already_released' => $alreadyReleasedCount,
                    'previous_status_breakdown' => $statusBreakdown
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error releasing salary: ' . $e->getMessage(),
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}"
            ], 500);
        }
    }

    /**
     * Release salary only for records with 'Initiated' status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function releaseSalaryInitiatedOnly(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
        ]);

        try {
            // Check if payroll entries exist for this period
            $payrollQuery = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $request->month);

            $allPayrollRecords = $payrollQuery->get();

            if ($allPayrollRecords->isEmpty()) {
                $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}";
                
                return response()->json([
                    'status' => false,
                    'message' => 'No payroll entries found for the specified period',
                    'filter' => $filterDescription,
                    'records_found' => 0
                ], 404);
            }

            // Get records with 'Initiated' status only
            $initiatedRecords = $allPayrollRecords->where('status', 'Initiated');

            if ($initiatedRecords->isEmpty()) {
                $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}";
                
                // Get current status breakdown
                $statusBreakdown = $allPayrollRecords->groupBy('status')->map(function ($group) {
                    return $group->count();
                })->toArray();

                return response()->json([
                    'status' => false,
                    'message' => 'No payroll entries with "Initiated" status found for the specified period',
                    'filter' => $filterDescription,
                    'total_records' => $allPayrollRecords->count(),
                    'initiated_records' => 0,
                    'current_status_breakdown' => $statusBreakdown
                ], 404);
            }

            // Get current status breakdown before update
            $statusBreakdown = $allPayrollRecords->groupBy('status')->map(function ($group) {
                return $group->count();
            })->toArray();

            // Update only 'Initiated' records to 'Released' status
            $updatedCount = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $request->month)
                ->where('status', 'Initiated') // Only update 'Initiated' records
                ->update([
                    'status' => 'Released',
                    'updated_at' => now()
                ]);

            $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}";

            // Count records by other statuses (excluding the ones we just updated)
            $otherStatusCount = $allPayrollRecords->where('status', '!=', 'Initiated')->count();

            $message = "Successfully released salary for {$updatedCount} employees with 'Initiated' status";
            if ($otherStatusCount > 0) {
                $message .= ". {$otherStatusCount} employees with other statuses were not affected";
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'filter' => $filterDescription,
                'summary' => [
                    'total_records' => $allPayrollRecords->count(),
                    'initiated_records_updated' => $updatedCount,
                    'other_status_records' => $otherStatusCount,
                    'previous_status_breakdown' => $statusBreakdown,
                    'updated_from' => 'Initiated',
                    'updated_to' => 'Released'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error releasing salary: ' . $e->getMessage(),
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}"
            ], 500);
        }
    }
}

