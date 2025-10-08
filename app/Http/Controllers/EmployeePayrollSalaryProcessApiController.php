<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeePayrollSalaryProcess;
use App\Models\EmployeeDetail;
use App\Models\EmploymentDetail;
use App\Exports\PayrollExport;
use App\Exports\ReleasedPayrollExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\PDF;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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
            'month' => 'required|string|max:30',
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
            'subBranch' => 'nullable|string|max:100', // Optional SubBranch filter
        ]);

        try {
            // Get payroll records
            $payrollRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $request->month)
                ->get();

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No payroll records found for the specified period');
            }

            // Get all employee codes from payroll records
            $empCodes = $payrollRecords->pluck('empCode')->unique()->toArray();
            
            // Fetch employee details and employment details with SubBranch filter
            $employeeDetails = EmployeeDetail::whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            
            $employmentDetailsQuery = EmploymentDetail::whereIn('EmpCode', $empCodes);
            
            // Apply SubBranch filter if provided
            if ($request->has('subBranch') && !empty($request->subBranch)) {
                $employmentDetailsQuery->where('SubBranch', $request->subBranch);
            }
            
            $employmentDetails = $employmentDetailsQuery->get()->keyBy('EmpCode');

            // Filter payroll records based on employment details (if SubBranch filter applied)
            if ($request->has('subBranch') && !empty($request->subBranch)) {
                $filteredEmpCodes = $employmentDetails->pluck('EmpCode')->toArray();
                $payrollRecords = $payrollRecords->whereIn('empCode', $filteredEmpCodes);
            }

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No payroll records found for the specified SubBranch and period');
            }

            $excelData = [];
            $dynamicHeaders = [];
            $totals = []; // For calculating column totals

            foreach ($payrollRecords as $record) {
                // Get employee details and employment details
                $employeeDetail = $employeeDetails->get($record->empCode);
                $employmentDetail = $employmentDetails->get($record->empCode);
                
                // Build full name using the helper method
                $fullName = $this->getFullEmployeeName($employeeDetail);

                // Parse JSON fields safely
                $grossList = $this->safeJsonDecode($record->grossList);
                $otherAllowances = $this->safeJsonDecode($record->otherAllowances);
                $otherBenefits = $this->safeJsonDecode($record->otherBenefits);
                $recurringDeductions = $this->safeJsonDecode($record->recurringDeduction);

                // Build dynamic headers using actual component names
                foreach ($grossList as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'gross_' . str_replace(' ', '_', strtolower($componentName));
                    $dynamicHeaders[$headerKey] = $componentName;
                }
                
                foreach ($otherAllowances as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'allowance_' . str_replace(' ', '_', strtolower($componentName));
                    $dynamicHeaders[$headerKey] = $componentName;
                }
                
                foreach ($otherBenefits as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'benefit_' . str_replace(' ', '_', strtolower($componentName));
                    $dynamicHeaders[$headerKey] = $componentName;
                }
                
                foreach ($recurringDeductions as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'deduction_' . str_replace(' ', '_', strtolower($componentName));
                    $dynamicHeaders[$headerKey] = $componentName;
                }

                // Calculate totals
                $monthlyTotalGross = 0;
                $monthlyTotalBenefits = 0;
                $monthlyTotalDeductions = 0;

                // Calculate gross total
                foreach ($grossList as $item) {
                    if (isset($item['calculatedValue'])) {
                        $monthlyTotalGross += (float)$item['calculatedValue'];
                    }
                }

                // Calculate benefits total (allowances + benefits)
                foreach ($otherAllowances as $item) {
                    if (isset($item['calculatedValue'])) {
                        $monthlyTotalBenefits += (float)$item['calculatedValue'];
                    }
                }
                foreach ($otherBenefits as $item) {
                    if (isset($item['calculatedValue'])) {
                        $monthlyTotalBenefits += (float)$item['calculatedValue'];
                    }
                }

                // Calculate deductions total
                foreach ($recurringDeductions as $item) {
                    if (isset($item['calculatedValue'])) {
                        $monthlyTotalDeductions += (float)$item['calculatedValue'];
                    }
                }

                // Calculate annual totals
                $annualTotalGross = $monthlyTotalGross * 12;
                $annualTotalBenefits = $monthlyTotalBenefits * 12;
                $annualTotalDeductions = $monthlyTotalDeductions * 12;
                $netTakeHomeMonthly = $monthlyTotalGross + $monthlyTotalBenefits - $monthlyTotalDeductions;

                // Build row data as associative array (including new columns)
                $row = [
                    'empCode' => $record->empCode,
                    'empName' => $fullName ?: 'N/A',
                    'designation' => $employmentDetail->Designation ?? 'N/A',
                    'dateOfJoining' => $employmentDetail->dateOfJoining ?? 'N/A',
                    'monthlyTotalGross' => round($monthlyTotalGross, 2),
                    'annualTotalGross' => round($annualTotalGross, 2),
                    'monthlyTotalBenefits' => round($monthlyTotalBenefits, 2),
                    'annualTotalBenefits' => round($annualTotalBenefits, 2),
                    'monthlyTotalRecurringDeductions' => round($monthlyTotalDeductions, 2),
                    'annualTotalRecurringDeductions' => round($annualTotalDeductions, 2),
                    'netTakeHomeMonthly' => round($netTakeHomeMonthly, 2),
                    'status' => $record->status,
                ];

                // Add dynamic values and calculate totals
                foreach ($grossList as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'gross_' . str_replace(' ', '_', strtolower($componentName));
                    $value = (float)($item['calculatedValue'] ?? 0);
                    $row[$headerKey] = $value;
                    $totals[$headerKey] = ($totals[$headerKey] ?? 0) + $value;
                }
                
                foreach ($otherAllowances as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'allowance_' . str_replace(' ', '_', strtolower($componentName));
                    $value = (float)($item['calculatedValue'] ?? 0);
                    $row[$headerKey] = $value;
                    $totals[$headerKey] = ($totals[$headerKey] ?? 0) + $value;
                }
                
                foreach ($otherBenefits as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'benefit_' . str_replace(' ', '_', strtolower($componentName));
                    $value = (float)($item['calculatedValue'] ?? 0);
                    $row[$headerKey] = $value;
                    $totals[$headerKey] = ($totals[$headerKey] ?? 0) + $value;
                }
                
                foreach ($recurringDeductions as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'deduction_' . str_replace(' ', '_', strtolower($componentName));
                    $value = (float)($item['calculatedValue'] ?? 0);
                    $row[$headerKey] = $value;
                    $totals[$headerKey] = ($totals[$headerKey] ?? 0) + $value;
                }

                // Add totals for summary columns
                $totals['monthlyTotalGross'] = ($totals['monthlyTotalGross'] ?? 0) + $monthlyTotalGross;
                $totals['annualTotalGross'] = ($totals['annualTotalGross'] ?? 0) + $annualTotalGross;
                $totals['monthlyTotalBenefits'] = ($totals['monthlyTotalBenefits'] ?? 0) + $monthlyTotalBenefits;
                $totals['annualTotalBenefits'] = ($totals['annualTotalBenefits'] ?? 0) + $annualTotalBenefits;
                $totals['monthlyTotalRecurringDeductions'] = ($totals['monthlyTotalRecurringDeductions'] ?? 0) + $monthlyTotalDeductions;
                $totals['annualTotalRecurringDeductions'] = ($totals['annualTotalRecurringDeductions'] ?? 0) + $annualTotalDeductions;
                $totals['netTakeHomeMonthly'] = ($totals['netTakeHomeMonthly'] ?? 0) + $netTakeHomeMonthly;

                // Initialize missing keys for dynamic headers in row
                foreach ($dynamicHeaders as $key => $value) {
                    if (!isset($row[$key])) {
                        $row[$key] = 0;
                    }
                }

                $excelData[] = $row;
            }

            // Create totals row
            $totalsRow = [
                'empCode' => 'TOTAL',
                'empName' => '',
                'designation' => '',
                'dateOfJoining' => '',
                'monthlyTotalGross' => round($totals['monthlyTotalGross'] ?? 0, 2),
                'annualTotalGross' => round($totals['annualTotalGross'] ?? 0, 2),
                'monthlyTotalBenefits' => round($totals['monthlyTotalBenefits'] ?? 0, 2),
                'annualTotalBenefits' => round($totals['annualTotalBenefits'] ?? 0, 2),
                'monthlyTotalRecurringDeductions' => round($totals['monthlyTotalRecurringDeductions'] ?? 0, 2),
                'annualTotalRecurringDeductions' => round($totals['annualTotalRecurringDeductions'] ?? 0, 2),
                'netTakeHomeMonthly' => round($totals['netTakeHomeMonthly'] ?? 0, 2),
                'status' => '',
            ];

            // Add dynamic totals
            foreach ($dynamicHeaders as $key => $value) {
                $totalsRow[$key] = round($totals[$key] ?? 0, 2);
            }

            // Add totals row to data
            $excelData[] = $totalsRow;

            // Company information for the heading
            $companyInfo = [
                'companyName' => $request->companyName,
                'year' => $request->year,
                'month' => $request->month,
                'subBranch' => $request->subBranch ?? 'All SubBranches'
            ];

            // Generate filename
            $subBranchSuffix = $request->has('subBranch') && !empty($request->subBranch) ? "_{$request->subBranch}" : '';
            $fileName = "Payroll_{$request->companyName}_{$request->year}_{$request->month}{$subBranchSuffix}.xlsx";

            return Excel::download(new PayrollExport($excelData, $dynamicHeaders, $companyInfo), $fileName);

        } catch (\Exception $e) {
            abort(500, 'Error in exporting payroll data: ' . $e->getMessage());
        }
    }

    /**
     * Export payroll data to Excel for employees with Released status only
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportReleasedPayrollExcel(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'subBranch' => 'nullable|string|max:100', // Optional SubBranch filter
        ]);

        try {
            // Get payroll records with Released status only
            $payrollRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $request->month)
                ->where('status', 'Released') // Filter only Released status
                ->get();

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No released payroll records found for the specified period');
            }

            // Get all employee codes from payroll records
            $empCodes = $payrollRecords->pluck('empCode')->unique()->toArray();
            
            // Fetch employee details and employment details with SubBranch filter
            $employeeDetails = EmployeeDetail::whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            
            $employmentDetailsQuery = EmploymentDetail::whereIn('EmpCode', $empCodes);
            
            // Apply SubBranch filter if provided
            if ($request->has('subBranch') && !empty($request->subBranch)) {
                $employmentDetailsQuery->where('SubBranch', $request->subBranch);
            }
            
            $employmentDetails = $employmentDetailsQuery->get()->keyBy('EmpCode');

            // Filter payroll records based on employment details (if SubBranch filter applied)
            if ($request->has('subBranch') && !empty($request->subBranch)) {
                $filteredEmpCodes = $employmentDetails->pluck('EmpCode')->toArray();
                $payrollRecords = $payrollRecords->whereIn('empCode', $filteredEmpCodes);
            }

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No released payroll records found for the specified SubBranch and period');
            }

            $excelData = [];
            $dynamicHeaders = [];
            $totals = []; // For calculating column totals
            $serialNo = 1; // For serial number

            // FIRST PASS: Collect ALL possible dynamic headers from ALL records
            foreach ($payrollRecords as $record) {
                $grossList = $this->safeJsonDecode($record->grossList);
                $recurringDeductions = $this->safeJsonDecode($record->recurringDeduction);

                // Build complete list of dynamic headers from all records
                foreach ($grossList as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'gross_' . str_replace(' ', '_', strtolower($componentName));
                    $dynamicHeaders[$headerKey] = $componentName;
                }
                
                foreach ($recurringDeductions as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'deduction_' . str_replace(' ', '_', strtolower($componentName));
                    $dynamicHeaders[$headerKey] = $componentName;
                }
            }

            // SECOND PASS: Process each record and ensure all dynamic columns have values
            foreach ($payrollRecords as $record) {
                // Get employee details and employment details
                $employeeDetail = $employeeDetails->get($record->empCode);
                $employmentDetail = $employmentDetails->get($record->empCode);
                
                // Build full name using the helper method
                $fullName = $this->getFullEmployeeName($employeeDetail);

                // Parse JSON fields safely - REMOVED otherAllowances and otherBenefits
                $grossList = $this->safeJsonDecode($record->grossList);
                $recurringDeductions = $this->safeJsonDecode($record->recurringDeduction);

                // Calculate totals - REMOVED ALL benefits calculation
                $monthlyTotalGross = 0;
                $monthlyTotalDeductions = 0;

                // Calculate gross total
                foreach ($grossList as $item) {
                    if (isset($item['calculatedValue']) && is_numeric($item['calculatedValue'])) {
                        $monthlyTotalGross += (float)$item['calculatedValue'];
                    }
                }

                // Calculate deductions total
                foreach ($recurringDeductions as $item) {
                    if (isset($item['calculatedValue']) && is_numeric($item['calculatedValue'])) {
                        $monthlyTotalDeductions += (float)$item['calculatedValue'];
                    }
                }

                // Calculate net salary - REMOVED benefits from calculation
                $netTakeHomeMonthly = $monthlyTotalGross - $monthlyTotalDeductions;

                // Build row data as associative array (with serial number and paid days)
                $row = [
                    'serialNo' => $serialNo++,
                    'empCode' => $record->empCode ?? '',
                    'empName' => $fullName ?: 'N/A',
                    'designation' => $employmentDetail->Designation ?? 'N/A',
                    'paidDays' => 0, // Currently set to 0 as attendance not calculated
                    'dateOfJoining' => $employmentDetail->dateOfJoining ?? 'N/A',
                    'monthlyTotalGross' => round($monthlyTotalGross, 2),
                    'monthlyTotalRecurringDeductions' => round($monthlyTotalDeductions, 2),
                    'netTakeHomeMonthly' => round($netTakeHomeMonthly, 2),
                    'status' => $record->status, // Will always be 'Released'
                ];

                // Initialize ALL dynamic headers with 0 first
                foreach ($dynamicHeaders as $headerKey => $headerName) {
                    $row[$headerKey] = 0;
                    if (!isset($totals[$headerKey])) {
                        $totals[$headerKey] = 0;
                    }
                }

                // Now populate actual values for gross components that exist
                foreach ($grossList as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'gross_' . str_replace(' ', '_', strtolower($componentName));
                    $value = 0;
                    if (isset($item['calculatedValue']) && is_numeric($item['calculatedValue'])) {
                        $value = (float)$item['calculatedValue'];
                    }
                    $row[$headerKey] = $value;
                    $totals[$headerKey] += $value;
                }
                
                // Now populate actual values for deduction components that exist
                foreach ($recurringDeductions as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'deduction_' . str_replace(' ', '_', strtolower($componentName));
                    $value = 0;
                    if (isset($item['calculatedValue']) && is_numeric($item['calculatedValue'])) {
                        $value = (float)$item['calculatedValue'];
                    }
                    $row[$headerKey] = $value;
                    $totals[$headerKey] += $value;
                }

                // Add totals for summary columns - REMOVED all benefits-related totals
                $totals['monthlyTotalGross'] = ($totals['monthlyTotalGross'] ?? 0) + $monthlyTotalGross;
                $totals['monthlyTotalRecurringDeductions'] = ($totals['monthlyTotalRecurringDeductions'] ?? 0) + $monthlyTotalDeductions;
                $totals['netTakeHomeMonthly'] = ($totals['netTakeHomeMonthly'] ?? 0) + $netTakeHomeMonthly;
                $totals['paidDays'] = ($totals['paidDays'] ?? 0) + 0; // Sum of paid days (0 for now)

                $excelData[] = $row;
            }

            // Create totals row - REMOVED all benefits-related fields
            $totalsRow = [
                'serialNo' => '',
                'empCode' => 'TOTAL',
                'empName' => '',
                'designation' => '',
                'paidDays' => round($totals['paidDays'] ?? 0, 0),
                'dateOfJoining' => '',
                'monthlyTotalGross' => round($totals['monthlyTotalGross'] ?? 0, 2),
                'monthlyTotalRecurringDeductions' => round($totals['monthlyTotalRecurringDeductions'] ?? 0, 2),
                'netTakeHomeMonthly' => round($totals['netTakeHomeMonthly'] ?? 0, 2),
                'status' => '',
            ];

            // Add dynamic totals with 0 default values
            foreach ($dynamicHeaders as $key => $value) {
                $totalsRow[$key] = round($totals[$key] ?? 0, 2);
            }

            // Add totals row to data
            $excelData[] = $totalsRow;

            // Company information for the heading
            $companyInfo = [
                'companyName' => $request->companyName,
                'year' => $request->year,
                'month' => $request->month,
                'subBranch' => $request->subBranch ?? 'All SubBranches'
            ];

            // Generate filename with Released indicator
            $subBranchSuffix = $request->has('subBranch') && !empty($request->subBranch) ? "_{$request->subBranch}" : '';
            $fileName = "SalarySheet_{$request->companyName}_{$request->month}_{$request->year}{$subBranchSuffix}.xlsx";

            // Use the ReleasedPayrollExport class
            return Excel::download(new ReleasedPayrollExport($excelData, $dynamicHeaders, $companyInfo), $fileName);

        } catch (\Exception $e) {
            abort(500, 'Error in exporting released payroll data: ' . $e->getMessage());
        }
    }

    /**
     * Safely decode JSON to array
     */
    private function safeJsonDecode($jsonString)
    {
        if (empty($jsonString) || !is_string($jsonString)) {
            return [];
        }

        $decoded = json_decode($jsonString, true);
        
        // If json_decode fails or returns non-array, return empty array
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
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

        // Calculate net salary (gross - deduction only, allowances are separate)
        $monthlyNetSalary = $monthlyGross - $monthlyDeduction;

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

    /**
     * Generate and download salary slip PDF for a specific employee
     *
     * @param Request $request
     * @param string $corpId
     * @param string $empCode
     * @param string $year
     * @param string $month
     * @param string|null $companyName
     * @return \Illuminate\Http\Response
     */
    public function downloadSalarySlipPdf($corpId, $empCode, $year, $month, $companyName = null)
    {
        try {
            // Start building the query
            $query = EmployeePayrollSalaryProcess::where('corpId', $corpId)
                ->where('empCode', $empCode)
                ->where('year', $year)
                ->where('month', $month);
            
            // Add company name filter if provided
            if ($companyName) {
                $query->where('companyName', $companyName);
            }
            
            // Get the payroll record
            $payroll = $query->first();

            if (!$payroll) {
                abort(404, 'Payroll record not found for the specified employee and period');
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

            // Get employee details
            $employeeDetail = EmployeeDetail::where('corp_id', $corpId)
                ->where('EmpCode', $empCode)
                ->first();

            $employmentDetail = EmploymentDetail::where('corp_id', $corpId)
                ->where('EmpCode', $empCode)
                ->first();

            // Prepare employee details for PDF
            $employeeDetails = [
                'full_name' => $this->getFullEmployeeName($employeeDetail),
                'designation' => $employmentDetail->Designation ?? 'N/A',
                'date_of_joining' => $employmentDetail->dateOfJoining ?? 'N/A',
                'department' => $employmentDetail->Department ?? 'N/A',
            ];

            // Prepare data for PDF
            $pdfData = [
                'id' => $payroll->id,
                'corpId' => $payroll->corpId,
                'empCode' => $payroll->empCode,
                'companyName' => $payroll->companyName,
                'year' => $payroll->year,
                'month' => $payroll->month,
                'status' => $payroll->status,
                'gross' => $grossList,
                'deductions' => $recurringDeduction,
                'otherBenefitsAllowances' => array_merge($otherBenefits, $otherAllowances),
            ];

            // Format summary to match expected structure
            $formattedSummary = [
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
            ];

            // Generate filename
            $filename = "SalarySlip_{$empCode}_{$month}_{$year}.pdf";

            // Generate PDF using Laravel Dompdf class
            $pdf = app(PDF::class);
            $pdf->loadView('salary-slip-pdf', [
                'data' => $pdfData,
                'employeeDetails' => $employeeDetails,
                'summary' => $formattedSummary
            ]);

            $pdf->setPaper('A4', 'portrait');

            // Return PDF as download
            return $pdf->download($filename);

        } catch (\Exception $e) {
            abort(500, 'Error generating salary slip PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate and download salary slip PDFs for all employees in a ZIP file
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadAllSalarySlipsPdf(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'status' => 'nullable|string', // Optional status filter
        ]);

        try {
            // Get payroll records
            $query = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $request->month);

            // Add status filter if provided
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            $payrollRecords = $query->get();

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No payroll records found for the specified period and criteria');
            }

            // Create temporary directory for PDF files
            $tempDir = storage_path('app/temp/salary_slips_' . time());
            File::makeDirectory($tempDir, 0755, true);

            // Get all employee codes
            $empCodes = $payrollRecords->pluck('empCode')->unique()->toArray();
            
            // Fetch employee details in bulk
            $employeeDetails = EmployeeDetail::whereIn('EmpCode', $empCodes)
                ->where('corp_id', $request->corpId)
                ->get()
                ->keyBy('EmpCode');
            
            $employmentDetails = EmploymentDetail::whereIn('EmpCode', $empCodes)
                ->where('corp_id', $request->corpId)
                ->get()
                ->keyBy('EmpCode');

            $generatedFiles = [];
            $errorCount = 0;

            foreach ($payrollRecords as $payroll) {
                try {
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

                    // Get employee details
                    $employeeDetail = $employeeDetails->get($payroll->empCode);
                    $employmentDetail = $employmentDetails->get($payroll->empCode);

                    // Prepare employee details for PDF
                    $empDetails = [
                        'full_name' => $this->getFullEmployeeName($employeeDetail),
                        'designation' => $employmentDetail->Designation ?? 'N/A',
                        'date_of_joining' => $employmentDetail->dateOfJoining ?? 'N/A',
                        'department' => $employmentDetail->Department ?? 'N/A',
                    ];

                    // Prepare data for PDF
                    $pdfData = [
                        'id' => $payroll->id,
                        'corpId' => $payroll->corpId,
                        'empCode' => $payroll->empCode,
                        'companyName' => $payroll->companyName,
                        'year' => $payroll->year,
                        'month' => $payroll->month,
                        'status' => $payroll->status,
                        'gross' => $grossList,
                        'deductions' => $recurringDeduction,
                        'otherBenefitsAllowances' => array_merge($otherBenefits, $otherAllowances),
                    ];

                    // Format summary to match expected structure
                    $formattedSummary = [
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
                    ];

                    // Generate PDF using Laravel Dompdf class
                    $pdf = app(PDF::class);
                    $pdf->loadView('salary-slip-pdf', [
                        'data' => $pdfData,
                        'employeeDetails' => $empDetails,
                        'summary' => $formattedSummary
                    ]);

                    $pdf->setPaper('A4', 'portrait');

                    // Save PDF to temporary directory
                    $filename = "SalarySlip_{$payroll->empCode}_{$request->month}_{$request->year}.pdf";
                    $filePath = $tempDir . '/' . $filename;
                    file_put_contents($filePath, $pdf->output());
                    $generatedFiles[] = $filePath;

                } catch (\Exception $e) {
                    $errorCount++;
                    // Log error but continue with other files
                    \Log::error("Error generating PDF for employee {$payroll->empCode}: " . $e->getMessage());
                }
            }

            if (empty($generatedFiles)) {
                // Clean up temp directory
                File::deleteDirectory($tempDir);
                abort(500, 'Failed to generate any PDF files');
            }

            // Create ZIP file
            $statusSuffix = $request->has('status') && !empty($request->status) ? "_{$request->status}" : '';
            $zipFilename = "SalarySlips_{$request->companyName}_{$request->month}_{$request->year}{$statusSuffix}.zip";
            $zipPath = $tempDir . '/' . $zipFilename;

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                foreach ($generatedFiles as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
            } else {
                // Clean up temp directory
                File::deleteDirectory($tempDir);
                abort(500, 'Failed to create ZIP file');
            }

            // Return ZIP file as download and clean up
            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            // Clean up temp directory if it exists
            if (isset($tempDir) && File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            abort(500, 'Error generating salary slip PDFs: ' . $e->getMessage());
        }
    }
}

