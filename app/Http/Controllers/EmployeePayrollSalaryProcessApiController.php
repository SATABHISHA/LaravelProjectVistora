<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeePayrollSalaryProcess;
use App\Models\EmployeeDetail;
use App\Models\EmploymentDetail;
use App\Models\EmployeeSalaryStructure;
use App\Exports\PayrollExport;
use App\Exports\ReleasedPayrollExport;
use App\Exports\PayrollArrearsExport;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;

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
        // Normalize month: accept numeric (1-12) or month name ("January", etc.)
        if (is_numeric($month) && $month >= 1 && $month <= 12) {
            // Convert numeric month to month name
            $month = date('F', mktime(0, 0, 0, (int)$month, 1));
        }
        // If already a month name, use as-is

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

        // Backward compatibility: if arrears exists in allowances for old data,
        // expose it in gross so payroll gross benefits UI can render it.
        $hasArrearsInGross = collect($grossList)->contains(function ($item) {
            return strtolower((string)($item['componentName'] ?? '')) === 'arrears';
        });
        if (!$hasArrearsInGross) {
            foreach ($otherAllowances as $allowance) {
                if (strtolower((string)($allowance['componentName'] ?? '')) === 'arrears') {
                    $grossList[] = $allowance;
                    break;
                }
            }
        }

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

        // Normalize month: convert numeric to month name
        $month = $request->month;
        if (is_numeric($month) && $month >= 1 && $month <= 12) {
            $month = date('F', mktime(0, 0, 0, (int)$month, 1));
        }
        // If already a month name, use as-is

        // Check if payroll already exists for this period
        $existingPayrollQuery = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
            ->where('year', $request->year)
            ->where('month', $month);

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

        // Build query for salary structures - MUST filter by year
        $query = \App\Models\EmployeeSalaryStructure::where('corpId', $request->corpId)
            ->where('year', $request->year);
        
        // Add company name filter if provided
        if ($request->has('companyName') && !empty($request->companyName)) {
            $query->where('companyName', $request->companyName);
        }
        
        // Get filtered salary structures for the specific year
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
                $arrearsContext = $this->calculateArrearsContextForStructure(
                    $structure,
                    $month,
                    $request->year
                );

                $grossList = $this->safeJsonDecode($structure->grossList);
                $grossList = $this->appendArrearsComponentToGross(
                    $grossList,
                    $arrearsContext['arrearsAmount']
                );

                // Create payroll entry (using create instead of updateOrCreate to prevent duplicates)
                EmployeePayrollSalaryProcess::create([
                    'corpId' => $structure->corpId,
                    'empCode' => $structure->empCode,
                    'companyName' => $structure->companyName,
                    'year' => $request->year,
                    'month' => $month, // Use normalized month name
                    'grossList' => json_encode($grossList),
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
            // Normalize month to numeric value for consistent queries
            $monthNumber = is_numeric($request->month) ? (int)$request->month : (int)date('n', strtotime($request->month));

            // Get payroll records - ensure strict year filtering
            $payrollRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $monthNumber)
                ->whereNotNull('year')
                ->where('year', '!=', '')
                ->get();

            // Log the query results for debugging
            Log::info("ExportPayrollExcel query results", [
                'corpId' => $request->corpId,
                'companyName' => $request->companyName,
                'year' => $request->year,
                'month' => $request->month,
                'record_count' => $payrollRecords->count(),
                'years_found' => $payrollRecords->pluck('year')->unique()->toArray(),
                'sample_records' => $payrollRecords->take(3)->map(function($r) {
                    return ['empCode' => $r->empCode, 'year' => $r->year, 'month' => $r->month];
                })->toArray()
            ]);

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

            // Ensure arrears stats are initialized (fallback when no arrears calculation was run)
            if (!isset($arrearsStats) || !is_array($arrearsStats)) {
                $arrearsStats = [
                    'totalEmployees' => 0,
                    'employeesWithArrears' => 0,
                    'employeesWithoutArrears' => 0,
                    'employeesWithoutRevision' => 0,
                    'employeesWithArrearsDetails' => [],
                    'employeesWithoutRevisionDetails' => []
                ];
            }

            // Company and arrears information
            $companyInfo = [
                'companyName' => $request->companyName,
                'year' => $request->year,
                'month' => $request->month,
                'subBranch' => $request->subBranch ?? 'All SubBranches'
            ];

            $arrearsInfo = [
                'totalEmployees' => $arrearsStats['totalEmployees'],
                'totalEmployeesWithArrears' => $arrearsStats['employeesWithArrears'],
                'employeesWithoutRevision' => $arrearsStats['employeesWithoutRevision'],
                'employeesWithArrearsDetails' => $arrearsStats['employeesWithArrearsDetails'],
                'employeesWithoutRevisionDetails' => $arrearsStats['employeesWithoutRevisionDetails']
            ];

            // Generate filename
            $subBranchSuffix = $request->has('subBranch') && !empty($request->subBranch) ? "_{$request->subBranch}" : '';
            $fileName = "SalarySheet_WithArrears_{$request->companyName}_{$request->month}_{$request->year}{$subBranchSuffix}.xlsx";

            // Note: We still generate Excel even if no arrears found
            // The Excel will show "No Arrears" status for employees without revision
            // Only return error if NO payroll records exist at all (handled earlier in the code)

            // Store Excel file temporarily, then return as download
            $tempPath = 'temp_arrears_export_' . time() . '.xlsx';
            Excel::store(new PayrollArrearsExport($excelData, $dynamicHeaders, $companyInfo, $arrearsInfo), $tempPath, 'local');

            if (Storage::exists($tempPath)) {
                $fullPath = storage_path('app/' . $tempPath);

                // Calculate arrears months range if available
                $arrearsMonthsRange = '';
                if ($arrearsStats['employeesWithArrears'] > 0 && !empty($arrearsStats['employeesWithArrearsDetails'])) {
                    $firstEmployee = $arrearsStats['employeesWithArrearsDetails'][0];
                    if (isset($firstEmployee['effectiveFrom']) && isset($firstEmployee['monthsCount'])) {
                        $arrearsMonthsRange = $firstEmployee['monthsCount'] . ' months (from ' . $firstEmployee['effectiveFrom'] . ')';
                    }
                }

                $headers = [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'X-Total-Employees' => $arrearsStats['totalEmployees'],
                    'X-Employees-With-Arrears' => $arrearsStats['employeesWithArrears'],
                    'X-Arrears-Months' => $arrearsMonthsRange,
                    'Access-Control-Expose-Headers' => 'X-Total-Employees, X-Employees-With-Arrears, X-Arrears-Months'
                ];

                // Return a BinaryFileResponse by using response()->download and remove the temp file after sending
                return response()->download($fullPath, $fileName, $headers)->deleteFileAfterSend(true);
            }

            // Fallback to original method
            return Excel::download(new PayrollArrearsExport($excelData, $dynamicHeaders, $companyInfo, $arrearsInfo), $fileName);

        } catch (\Exception $e) {
            abort(500, 'Error in exporting payroll data with arrears: ' . $e->getMessage());
        }
    }

    /**
     * Export released payroll data to Excel (only Released status records)
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
     * Normalize month value to the month-name format used in payroll records.
     */
    private function normalizeMonthName($month)
    {
        if (is_numeric($month) && $month >= 1 && $month <= 12) {
            return date('F', mktime(0, 0, 0, (int)$month, 1));
        }

        return ucfirst(strtolower((string)$month));
    }

    /**
     * Compute arrears payout context from salary revision details.
     */
    private function calculateArrearsContextForStructure($salaryStructure, $payrollMonthName, $payrollYear)
    {
        $hasRevisionWithArrears =
            !empty($salaryStructure->salaryRevisionMonth) &&
            !empty($salaryStructure->arrearWithEffectFrom) &&
            ((float)($salaryStructure->increment ?? 0) > 0);

        if (!$hasRevisionWithArrears) {
            return [
                'arrearsAmount' => 0,
                'arrearsMonths' => 0,
            ];
        }

        try {
            $raw = (string)$salaryStructure->arrearWithEffectFrom;

            try {
                $effectiveFrom = \Carbon\Carbon::createFromFormat('d/m/Y', $raw);
            } catch (\Exception $e) {
                $effectiveFrom = \Carbon\Carbon::parse($raw);
            }

            $currentMonth = \Carbon\Carbon::createFromFormat(
                'Y-F-d',
                $payrollYear . '-' . $payrollMonthName . '-01'
            );

            $effectiveMonthStart = $effectiveFrom->copy()->startOfMonth();
            $currentMonthStart = $currentMonth->copy()->startOfMonth();

            if (!$effectiveFrom || !$effectiveMonthStart->lt($currentMonthStart)) {
                return [
                    'arrearsAmount' => 0,
                    'arrearsMonths' => 0,
                ];
            }

            // If arrears were already settled in a previous released payroll,
            // do not keep carrying the same revision arrears to future months.
            if ($this->hasPriorArrearsSettlement($salaryStructure, $effectiveMonthStart, $currentMonthStart)) {
                return [
                    'arrearsAmount' => 0,
                    'arrearsMonths' => 0,
                ];
            }

            $monthsDiff = $effectiveMonthStart->diffInMonths($currentMonthStart);
            $incrementAmount = (float)($salaryStructure->increment ?? 0);
            $arrearsAmount = round($incrementAmount * $monthsDiff, 2);

            return [
                'arrearsAmount' => $arrearsAmount,
                'arrearsMonths' => $monthsDiff,
            ];
        } catch (\Exception $e) {
            return [
                'arrearsAmount' => 0,
                'arrearsMonths' => 0,
            ];
        }
    }

    /**
     * Parse payroll month/year values into a month-start Carbon instance.
     */
    private function parsePayrollMonthStart($year, $month)
    {
        try {
            $monthName = $this->normalizeMonthName($month);
            return \Carbon\Carbon::createFromFormat('Y-F-d', ((string)$year) . '-' . $monthName . '-01')->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract arrears amount from gross/allowance payloads.
     */
    private function extractArrearsAmountFromPayload(array $grossList, array $otherAllowances)
    {
        foreach ($grossList as $item) {
            if (strtolower((string)($item['componentName'] ?? '')) === 'arrears') {
                return (float)($item['calculatedValue'] ?? 0);
            }
        }

        foreach ($otherAllowances as $item) {
            if (strtolower((string)($item['componentName'] ?? '')) === 'arrears') {
                return (float)($item['calculatedValue'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Check whether arrears for the current revision were already released earlier.
     */
    private function hasPriorArrearsSettlement($salaryStructure, $effectiveMonthStart, $currentMonthStart)
    {
        $priorReleasedPayrolls = EmployeePayrollSalaryProcess::where('corpId', $salaryStructure->corpId)
            ->where('companyName', $salaryStructure->companyName)
            ->where('empCode', $salaryStructure->empCode)
            ->where('status', 'Released')
            ->get(['year', 'month', 'grossList', 'otherAllowances']);

        foreach ($priorReleasedPayrolls as $payroll) {
            $payrollMonthStart = $this->parsePayrollMonthStart($payroll->year, $payroll->month);
            if (!$payrollMonthStart) {
                continue;
            }

            if (!$payrollMonthStart->gte($effectiveMonthStart) || !$payrollMonthStart->lt($currentMonthStart)) {
                continue;
            }

            $grossList = $this->safeJsonDecode($payroll->grossList);
            $otherAllowances = $this->safeJsonDecode($payroll->otherAllowances);
            $arrearsAmount = $this->extractArrearsAmountFromPayload($grossList, $otherAllowances);

            if ($arrearsAmount > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch attendance summary for payslip by trying month name first, then month number.
     */
    private function getAttendanceSummaryForPayslip($corpId, $empCode, $year, $monthName, $monthNumber)
    {
        $query = \App\Models\EmployeeAttendanceSummary::where('corpId', $corpId)
            ->where('empCode', $empCode)
            ->where('year', $year);

        $summary = (clone $query)->where('month', $monthName)->first();
        if ($summary) {
            return $summary;
        }

        return (clone $query)->where('month', (string)$monthNumber)->first();
    }

    /**
     * Derive attendance arrear days from available summary fields.
     */
    private function resolveAttendanceArrearDays($attendanceSummary)
    {
        if (!$attendanceSummary) {
            return 0;
        }

        $candidateKeys = [
            'attendanceArrearDays',
            'attendance_arrear_days',
            'arrearDays',
            'arrear_days',
            'attendanceArrear',
            'attendance_arrear',
        ];

        foreach ($candidateKeys as $key) {
            $value = data_get($attendanceSummary, $key);
            if ($value !== null && is_numeric($value)) {
                return (float)$value;
            }
        }

        return 0;
    }

    /**
     * Convert revision effective date to increment arrear days for the payslip month.
     */
    private function calculateIncrementArrearDaysForStructure($salaryStructure, $payrollMonthName, $payrollYear)
    {
        if (!$salaryStructure || empty($salaryStructure->arrearWithEffectFrom)) {
            return 0;
        }

        try {
            $raw = (string)$salaryStructure->arrearWithEffectFrom;
            try {
                $effectiveFrom = \Carbon\Carbon::createFromFormat('d/m/Y', $raw)->startOfDay();
            } catch (\Exception $e) {
                $effectiveFrom = \Carbon\Carbon::parse($raw)->startOfDay();
            }

            $currentMonth = \Carbon\Carbon::createFromFormat(
                'Y-F-d',
                $payrollYear . '-' . $payrollMonthName . '-01'
            )->startOfDay();

            if (!$effectiveFrom->lt($currentMonth)) {
                return 0;
            }

            return (float)$effectiveFrom->diffInDays($currentMonth);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Build earnings rows with split monthly/arrear columns for payslip rendering.
     */
    private function buildPayslipEarningsBreakup(array $grossList, array $otherAllowances, array $otherBenefits)
    {
        $rows = [];
        $totalMonthly = 0;
        $totalArrear = 0;
        $hasArrearRow = false;

        $allEarnings = array_merge($grossList, $otherAllowances, $otherBenefits);
        foreach ($allEarnings as $item) {
            $componentName = (string)($item['componentName'] ?? 'Component');
            $value = (float)($item['calculatedValue'] ?? 0);
            $isArrear = strtolower($componentName) === 'arrears';

            if ($isArrear) {
                $hasArrearRow = true;
                $rows[] = [
                    'componentName' => 'Arrears',
                    'monthlyValue' => 0,
                    'arrearValue' => round($value, 2),
                    'totalValue' => round($value, 2),
                ];
                $totalArrear += $value;
                continue;
            }

            $rows[] = [
                'componentName' => $componentName,
                'monthlyValue' => round($value, 2),
                'arrearValue' => 0,
                'totalValue' => round($value, 2),
            ];
            $totalMonthly += $value;
        }

        if (!$hasArrearRow) {
            foreach ($otherAllowances as $allowance) {
                if (strtolower((string)($allowance['componentName'] ?? '')) === 'arrears') {
                    $value = (float)($allowance['calculatedValue'] ?? 0);
                    if ($value > 0) {
                        $rows[] = [
                            'componentName' => 'Arrears',
                            'monthlyValue' => 0,
                            'arrearValue' => round($value, 2),
                            'totalValue' => round($value, 2),
                        ];
                        $totalArrear += $value;
                    }
                    break;
                }
            }
        }

        return [
            'rows' => $rows,
            'totalMonthly' => round($totalMonthly, 2),
            'totalArrear' => round($totalArrear, 2),
            'totalCombined' => round($totalMonthly + $totalArrear, 2),
        ];
    }

    /**
     * Ensure a single arrears component exists in allowances when amount > 0.
     */
    private function appendArrearsComponentToAllowances(array $allowances, $arrearsAmount)
    {
        $cleaned = $this->removeArrearsComponentFromAllowances($allowances);

        if ((float)$arrearsAmount <= 0) {
            return $cleaned;
        }

        $cleaned[] = [
            'componentName' => 'Arrears',
            'payType' => 'Fixed Amount',
            'paymentNature' => 'Payroll Arrears',
            'formula' => 'Revision arrears payout',
            'calculatedValue' => round((float)$arrearsAmount, 2),
            'annualCalculatedValue' => round((float)$arrearsAmount, 2),
            'isPartOfCtcYn' => 0,
            'componentDescription' => 'Auto-calculated arrears based on revision and effective month'
        ];

        return $cleaned;
    }

    /**
     * Ensure a single arrears component exists in gross list when amount > 0.
     */
    private function appendArrearsComponentToGross(array $grossList, $arrearsAmount)
    {
        $cleaned = $this->removeArrearsComponentFromGross($grossList);

        if ((float)$arrearsAmount <= 0) {
            return $cleaned;
        }

        $cleaned[] = [
            'componentName' => 'Arrears',
            'payType' => 'Fixed Amount',
            'paymentNature' => 'Payroll Arrears',
            'formula' => 'Revision arrears payout',
            'calculatedValue' => round((float)$arrearsAmount, 2),
            'annualCalculatedValue' => round((float)$arrearsAmount, 2),
            'isPartOfCtcYn' => 0,
            'componentDescription' => 'Auto-calculated arrears based on revision and effective month'
        ];

        return $cleaned;
    }

    private function removeArrearsComponentFromAllowances(array $allowances)
    {
        return array_values(array_filter($allowances, function ($item) {
            return strtolower((string)($item['componentName'] ?? '')) !== 'arrears';
        }));
    }

    private function removeArrearsComponentFromGross(array $grossList)
    {
        return array_values(array_filter($grossList, function ($item) {
            return strtolower((string)($item['componentName'] ?? '')) !== 'arrears';
        }));
    }

    /**
     * Resolve payroll status after release mode is applied.
     */
    private function deriveReleasedStatusByMode($releaseMode, $arrearsAmount)
    {
        if ($releaseMode === 'incremented_only' && (float)$arrearsAmount > 0) {
            return 'Increment Released';
        }

        return 'Released';
    }

    /**
     * Build payroll line items according to requested release mode.
     */
    private function buildPayrollPayloadByReleaseMode($payrollRecord, $releaseMode)
    {
        $grossList = $this->safeJsonDecode($payrollRecord->grossList);
        $otherAllowances = $this->safeJsonDecode($payrollRecord->otherAllowances);
        $otherBenefits = $this->safeJsonDecode($payrollRecord->otherBenefits);
        $recurringDeduction = $this->safeJsonDecode($payrollRecord->recurringDeduction);

        $structure = EmployeeSalaryStructure::where('corpId', $payrollRecord->corpId)
            ->where('companyName', $payrollRecord->companyName)
            ->where('empCode', $payrollRecord->empCode)
            ->where('year', $payrollRecord->year)
            ->first();

        $usedPreviousSalary = false;
        $arrearsAmount = 0;

        if ($structure) {
            $arrearsContext = $this->calculateArrearsContextForStructure(
                $structure,
                $payrollRecord->month,
                $payrollRecord->year
            );
            $arrearsAmount = (float)($arrearsContext['arrearsAmount'] ?? 0);
        }

        if ($releaseMode === 'previous_salary') {
            $previousStructure = EmployeeSalaryStructure::where('corpId', $payrollRecord->corpId)
                ->where('companyName', $payrollRecord->companyName)
                ->where('empCode', $payrollRecord->empCode)
                ->where('year', '<', $payrollRecord->year)
                ->orderBy('year', 'desc')
                ->first();

            if ($previousStructure) {
                $grossList = $this->safeJsonDecode($previousStructure->grossList);
                $otherAllowances = $this->safeJsonDecode($previousStructure->otherAlowances);
                $otherBenefits = $this->safeJsonDecode($previousStructure->otherBenifits);
                $recurringDeduction = $this->safeJsonDecode($previousStructure->recurringDeductions);
                $usedPreviousSalary = true;
            }

            $grossList = $this->removeArrearsComponentFromGross($grossList);
            $otherAllowances = $this->removeArrearsComponentFromAllowances($otherAllowances);
        } elseif ($releaseMode === 'incremented_only') {
            $grossList = $this->removeArrearsComponentFromGross($grossList);
            $otherAllowances = $this->removeArrearsComponentFromAllowances($otherAllowances);
        } else {
            if ($structure) {
                $grossList = $this->appendArrearsComponentToGross($grossList, $arrearsAmount);
                $otherAllowances = $this->removeArrearsComponentFromAllowances($otherAllowances);
            }
        }

        return [
            'grossList' => $grossList,
            'otherAllowances' => $otherAllowances,
            'otherBenefits' => $otherBenefits,
            'recurringDeduction' => $recurringDeduction,
            'arrearsAmount' => $arrearsAmount,
            'usedPreviousSalary' => $usedPreviousSalary,
        ];
    }

    /**
     * Get list of all branches for a company (for filtering purposes)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBranchList(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100'
        ]);

        try {
            // Get all unique branches and sub-branches for the company
            $branches = EmploymentDetail::where('corp_id', $request->corpId)
                ->where('company_name', $request->companyName)
                ->select('Branch', 'SubBranch')
                ->distinct()
                ->whereNotNull('Branch')
                ->orderBy('Branch')
                ->orderBy('SubBranch')
                ->get();

            $branchList = [];
            $branchSummary = [];

            foreach ($branches as $branch) {
                $branchName = $branch->Branch ?? 'N/A';
                $subBranchName = $branch->SubBranch ?? 'N/A';

                $branchList[] = [
                    'branch' => $branchName,
                    'sub_branch' => $subBranchName,
                    'combined_name' => $branchName . ($subBranchName !== 'N/A' ? ' - ' . $subBranchName : '')
                ];

                // Count employees per branch
                if (!isset($branchSummary[$branchName])) {
                    $branchSummary[$branchName] = [
                        'branch_name' => $branchName,
                        'sub_branches' => [],
                        'total_employees' => 0
                    ];
                }

                $employeeCount = EmploymentDetail::where('corp_id', $request->corpId)
                    ->where('company_name', $request->companyName)
                    ->where('Branch', $branchName)
                    ->where('SubBranch', $subBranchName)
                    ->count();

                $branchSummary[$branchName]['sub_branches'][] = [
                    'sub_branch_name' => $subBranchName,
                    'employee_count' => $employeeCount
                ];
                $branchSummary[$branchName]['total_employees'] += $employeeCount;
            }

            return response()->json([
                'status' => true,
                'message' => 'Branch list retrieved successfully',
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}",
                'data' => [
                    'all_branches' => $branchList,
                    'branch_summary' => array_values($branchSummary),
                    'unique_branches' => array_unique(collect($branchList)->pluck('branch')->toArray()),
                    'unique_sub_branches' => array_unique(collect($branchList)->pluck('sub_branch')->toArray())
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving branch list: ' . $e->getMessage(),
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}",
                'data' => []
            ], 500);
        }
    }


    private function numberToWords($number)
    {
        // Check if the intl extension is enabled
        if (class_exists('NumberFormatter')) {
            // Use NumberFormatter if available (preferred method)
            $formatter = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
            $inWords = $formatter->format($number);
            return ucwords($inWords) . ' Only';
        }
        
        // Fallback: Manual conversion for Indian currency system
        if ($number == 0) {
            return 'Zero Only';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

        $convertTwoDigits = function($num) use ($ones, $tens, $teens) {
            if ($num < 10) return $ones[$num];
            if ($num >= 10 && $num < 20) return $teens[$num - 10];
            return $tens[floor($num / 10)] . ($num % 10 ? ' ' . $ones[$num % 10] : '');
        };

        // Split into integer and decimal parts
        $parts = explode('.', number_format($number, 2, '.', ''));
        $integerPart = (int)$parts[0];
        $decimalPart = isset($parts[1]) ? (int)$parts[1] : 0;

        $words = '';

        // Crore (10,000,000)
        if ($integerPart >= 10000000) {
            $crore = floor($integerPart / 10000000);
            $words .= $convertTwoDigits($crore) . ' Crore ';
            $integerPart %= 10000000;
        }

        // Lakh (100,000)
        if ($integerPart >= 100000) {
            $lakh = floor($integerPart / 100000);
            $words .= $convertTwoDigits($lakh) . ' Lakh ';
            $integerPart %= 100000;
        }

        // Thousand (1,000)
        if ($integerPart >= 1000) {
            $thousand = floor($integerPart / 1000);
            $words .= $convertTwoDigits($thousand) . ' Thousand ';
            $integerPart %= 1000;
        }

        // Hundred (100)
        if ($integerPart >= 100) {
            $hundred = floor($integerPart / 100);
            $words .= $ones[$hundred] . ' Hundred ';
            $integerPart %= 100;
        }

        // Remaining two digits
        if ($integerPart > 0) {
            $words .= $convertTwoDigits($integerPart);
        }

        // Add decimal part (paise)
        if ($decimalPart > 0) {
            $words = trim($words) . ' Rupees and ' . $convertTwoDigits($decimalPart) . ' Paise';
        } else {
            $words = trim($words) . ' Rupees';
        }

        return trim($words) . ' Only';
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
            // Normalize month: accept numeric (1-12) or month name ("January", etc.)
            if (is_numeric($month) && $month >= 1 && $month <= 12) {
                // Convert numeric month to month name
                $month = date('F', mktime(0, 0, 0, (int)$month, 1));
            }
            // If already a month name, use as-is

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
     * Release salary for all employees in a specific period
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
            'releaseMode' => 'nullable|string|in:incremented_with_arrears,incremented_only,previous_salary',
            'empCodes' => 'nullable|array',
            'empCodes.*' => 'string|max:20',
        ]);

        try {
            $releaseMode = $request->input('releaseMode', 'incremented_with_arrears');

            // Normalize month: accept numeric (1-12) or month name ("January", etc.)
            $month = $request->month;
            if (is_numeric($month) && $month >= 1 && $month <= 12) {
                // Convert numeric month to month name
                $month = date('F', mktime(0, 0, 0, (int)$month, 1));
            }
            // If already a month name, use as-is

            // Check if payroll entries exist for this period
            $payrollQuery = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month);

            if ($request->has('empCodes') && is_array($request->empCodes) && !empty($request->empCodes)) {
                $payrollQuery->whereIn('empCode', $request->empCodes);
            }

            // Log the SQL query for debugging
            Log::info('Release Salary Query', [
                'corpId' => $request->corpId,
                'companyName' => $request->companyName,
                'year' => $request->year,
                'month_original' => $request->month,
                'month_normalized' => $month,
                'sql' => $payrollQuery->toSql(),
                'bindings' => $payrollQuery->getBindings()
            ]);

            $payrollRecords = $payrollQuery->get();

            if ($payrollRecords->isEmpty()) {
                $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}";
                
                // Log for debugging
                Log::warning('No payroll records found', [
                    'filter' => $filterDescription,
                    'query_result_count' => $payrollRecords->count()
                ]);
                
                return response()->json([
                    'status' => false,
                    'message' => 'No payroll entries found for the specified period',
                    'filter' => $filterDescription,
                    'records_found' => 0,
                    'debug_info' => [
                        'month_received' => $request->month,
                        'month_used' => $month
                    ]
                ], 404);
            }

            // Get current status breakdown before update
            $statusBreakdown = $payrollRecords->groupBy('status')->map(function ($group) {
                return $group->count();
            })->toArray();

            // Count records that are already released
            $alreadyReleasedCount = $payrollRecords->where('status', 'Released')->count();
            $toBeUpdatedCount = $payrollRecords->where('status', '!=', 'Released')->count();

            $toUpdateRecords = $payrollRecords->where('status', '!=', 'Released');
            $updatedCount = 0;
            $releasedWithArrearsCount = 0;
            $previousSalaryAppliedCount = 0;
            $modeRestrictedSkippedCount = 0;

            foreach ($toUpdateRecords as $record) {
                $currentStatus = strtolower((string)$record->status);

                // Once incremented salary is already released, do not allow fallback to previous salary.
                if ($releaseMode === 'previous_salary' && in_array($currentStatus, ['released', 'increment released'], true)) {
                    $modeRestrictedSkippedCount++;
                    continue;
                }

                $payload = $this->buildPayrollPayloadByReleaseMode($record, $releaseMode);
                $nextStatus = $this->deriveReleasedStatusByMode($releaseMode, $payload['arrearsAmount'] ?? 0);

                $record->grossList = json_encode($payload['grossList']);
                $record->otherAllowances = json_encode($payload['otherAllowances']);
                $record->otherBenefits = json_encode($payload['otherBenefits']);
                $record->recurringDeduction = json_encode($payload['recurringDeduction']);
                $record->status = $nextStatus;
                $record->updated_at = now();
                $record->save();

                if (($payload['arrearsAmount'] ?? 0) > 0) {
                    $releasedWithArrearsCount++;
                }
                if (!empty($payload['usedPreviousSalary'])) {
                    $previousSalaryAppliedCount++;
                }

                $updatedCount++;
            }

            $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}";

            // Prepare response message
            if ($updatedCount > 0) {
                $message = "Successfully released salary for {$updatedCount} employees";
                if ($alreadyReleasedCount > 0) {
                    $message .= ". {$alreadyReleasedCount} employees were already in 'Released' status";
                }
                if ($modeRestrictedSkippedCount > 0) {
                    $message .= ". {$modeRestrictedSkippedCount} employees were skipped due to release mode restrictions";
                }
            } else {
                $message = "No records were updated for the selected release mode";
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'filter' => $filterDescription,
                'summary' => [
                    'total_records' => $payrollRecords->count(),
                    'updated_to_released' => $updatedCount,
                    'already_released' => $alreadyReleasedCount,
                    'previous_status_breakdown' => $statusBreakdown,
                    'release_mode_applied' => $releaseMode,
                    'released_with_arrears' => $releasedWithArrearsCount,
                    'released_with_previous_salary' => $previousSalaryAppliedCount,
                    'mode_restricted_skipped' => $modeRestrictedSkippedCount
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
            'releaseMode' => 'nullable|string|in:incremented_with_arrears,incremented_only,previous_salary',
            'empCodes' => 'nullable|array',
            'empCodes.*' => 'string|max:20',
        ]);

        try {
            $releaseMode = $request->input('releaseMode', 'incremented_with_arrears');

            // Normalize month: accept numeric (1-12) or month name ("January", etc.)
            $month = $request->month;
            if (is_numeric($month) && $month >= 1 && $month <= 12) {
                // Convert numeric month to month name
                $month = date('F', mktime(0, 0, 0, (int)$month, 1));
            }
            // If already a month name, use as-is

            // Check if payroll entries exist for this period
            $payrollQuery = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month);

            if ($request->has('empCodes') && is_array($request->empCodes) && !empty($request->empCodes)) {
                $payrollQuery->whereIn('empCode', $request->empCodes);
            }

            $allPayrollRecords = $payrollQuery->get();

            if ($allPayrollRecords->isEmpty()) {
                $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}";
                
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
                $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}";
                
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

            $updatedCount = 0;
            $releasedWithArrearsCount = 0;
            $previousSalaryAppliedCount = 0;

            foreach ($initiatedRecords as $record) {
                $payload = $this->buildPayrollPayloadByReleaseMode($record, $releaseMode);
                $nextStatus = $this->deriveReleasedStatusByMode($releaseMode, $payload['arrearsAmount'] ?? 0);

                $record->grossList = json_encode($payload['grossList']);
                $record->otherAllowances = json_encode($payload['otherAllowances']);
                $record->otherBenefits = json_encode($payload['otherBenefits']);
                $record->recurringDeduction = json_encode($payload['recurringDeduction']);
                $record->status = $nextStatus;
                $record->updated_at = now();
                $record->save();

                if (($payload['arrearsAmount'] ?? 0) > 0) {
                    $releasedWithArrearsCount++;
                }
                if (!empty($payload['usedPreviousSalary'])) {
                    $previousSalaryAppliedCount++;
                }

                $updatedCount++;
            }

            $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}";

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
                    'updated_to' => 'Released',
                    'release_mode_applied' => $releaseMode,
                    'released_with_arrears' => $releasedWithArrearsCount,
                    'released_with_previous_salary' => $previousSalaryAppliedCount
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
     * Download salary slip PDF for a single employee (original design)
     *
     * @param string $corpId
     * @param string $empCode
     * @param string $year
     * @param string $month
     * @param string|null $companyName
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadSalarySlipPdf($corpId, $empCode, $year, $month, $companyName = null)
    {
        try {
            // Normalize month: accept numeric (1-12) or month name ("January", etc.)
            if (is_numeric($month)) {
                $monthNumber = (int)$month;
                $monthName = date('F', mktime(0, 0, 0, $monthNumber, 1));
            } else {
                $monthName = ucfirst(strtolower($month));
                $monthNumber = (int)date('n', strtotime($monthName));
            }

            $query = EmployeePayrollSalaryProcess::where('corpId', $corpId)
                ->where('empCode', $empCode)
                ->where('year', $year)
                ->where('month', $monthName);

            if ($companyName) {
                $query->where('companyName', $companyName);
            }

            $payroll = $query->first();

            if (!$payroll) {
                abort(404, 'Payroll record not found for the specified employee and period.');
            }

            $employeeDetail = EmployeeDetail::where('corp_id', $corpId)->where('EmpCode', $empCode)->first();
            $employmentDetail = EmploymentDetail::where('corp_id', $corpId)->where('EmpCode', $empCode)->first();

            // Get full employee name
            $fullName = $this->getFullEmployeeName($employeeDetail);

            // Decode JSON data
            $grossList = json_decode($payroll->grossList, true) ?: [];
            $otherBenefitsAllowances = array_merge(
                json_decode($payroll->otherAllowances, true) ?: [],
                json_decode($payroll->otherBenefits, true) ?: []
            );
            $deductions = json_decode($payroll->recurringDeduction, true) ?: [];

            // Calculate totals
            $totalGrossMonthly = array_sum(array_column($grossList, 'calculatedValue'));
            $totalBenefitsMonthly = array_sum(array_column($otherBenefitsAllowances, 'calculatedValue'));
            $totalDeductionsMonthly = array_sum(array_column($deductions, 'calculatedValue'));
            $netSalaryMonthly = $totalGrossMonthly + $totalBenefitsMonthly - $totalDeductionsMonthly;

            $data = [
                'corpId' => $corpId,
                'empCode' => $empCode,
                'companyName' => $payroll->companyName,
                'year' => $year,
                'month' => $monthName,
                'status' => $payroll->status,
                'gross' => $grossList,
                'otherBenefitsAllowances' => $otherBenefitsAllowances,
                'deductions' => $deductions,
            ];

            $employeeDetails = [
                'full_name' => $fullName,
                'designation' => $employmentDetail->Designation ?? 'N/A',
                'department' => $employmentDetail->Department ?? 'N/A',
                'date_of_joining' => $employmentDetail->dateOfJoining ?? 'N/A',
            ];

            $summary = [
                'totalGross' => ['monthly' => $totalGrossMonthly],
                'totalBenefits' => ['monthly' => $totalBenefitsMonthly],
                'totalDeductions' => ['monthly' => $totalDeductionsMonthly],
                'netSalary' => ['monthly' => $netSalaryMonthly],
            ];

            // Generate PDF using Dompdf
            $html = view('salary-slip-pdf', compact('data', 'employeeDetails', 'summary'))->render();

            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = "Salary_Slip_{$empCode}_{$monthName}_{$year}.pdf";

            return response()->streamDownload(function() use ($dompdf) {
                echo $dompdf->output();
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating salary slip PDF: ' . $e->getMessage());
            abort(500, 'Error generating salary slip: ' . $e->getMessage());
        }
    }

    /**
     * Generate and download salary slip PDFs for all employees in a ZIP file (original design)
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadAllSalarySlipsPdf(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'status' => 'nullable|string',
        ]);

        try {
            // Normalize month
            if (is_numeric($request->month)) {
                $monthNumber = (int)$request->month;
                $monthName = date('F', mktime(0, 0, 0, $monthNumber, 1));
            } else {
                $monthName = ucfirst(strtolower($request->month));
                $monthNumber = (int)date('n', strtotime($monthName));
            }

            $query = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $monthName);

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            $payrollRecords = $query->get();

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No payroll records found for the specified criteria.');
            }

            $empCodes = $payrollRecords->pluck('empCode')->unique()->toArray();
            $employeeDetails = EmployeeDetail::where('corp_id', $request->corpId)->whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            $employmentDetails = EmploymentDetail::where('corp_id', $request->corpId)->whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');

            $tempDir = storage_path('app/temp/salary_slips_' . time());
            File::makeDirectory($tempDir, 0755, true);
            $generatedFiles = [];

            foreach ($payrollRecords as $payroll) {
                $empCode = $payroll->empCode;
                $employee = $employeeDetails->get($empCode);
                $employment = $employmentDetails->get($empCode);

                $fullName = $this->getFullEmployeeName($employee);

                $grossList = json_decode($payroll->grossList, true) ?: [];
                $otherBenefitsAllowances = array_merge(
                    json_decode($payroll->otherAllowances, true) ?: [],
                    json_decode($payroll->otherBenefits, true) ?: []
                );
                $deductions = json_decode($payroll->recurringDeduction, true) ?: [];

                $totalGrossMonthly = array_sum(array_column($grossList, 'calculatedValue'));
                $totalBenefitsMonthly = array_sum(array_column($otherBenefitsAllowances, 'calculatedValue'));
                $totalDeductionsMonthly = array_sum(array_column($deductions, 'calculatedValue'));
                $netSalaryMonthly = $totalGrossMonthly + $totalBenefitsMonthly - $totalDeductionsMonthly;

                $data = [
                    'corpId' => $request->corpId,
                    'empCode' => $empCode,
                    'companyName' => $payroll->companyName,
                    'year' => $request->year,
                    'month' => $monthName,
                    'status' => $payroll->status,
                    'gross' => $grossList,
                    'otherBenefitsAllowances' => $otherBenefitsAllowances,
                    'deductions' => $deductions,
                ];

                $employeeDetailsData = [
                    'full_name' => $fullName,
                    'designation' => $employment->Designation ?? 'N/A',
                    'department' => $employment->Department ?? 'N/A',
                    'date_of_joining' => $employment->dateOfJoining ?? 'N/A',
                ];

                $summary = [
                    'totalGross' => ['monthly' => $totalGrossMonthly],
                    'totalBenefits' => ['monthly' => $totalBenefitsMonthly],
                    'totalDeductions' => ['monthly' => $totalDeductionsMonthly],
                    'netSalary' => ['monthly' => $netSalaryMonthly],
                ];

                $html = view('salary-slip-pdf', compact('data', 'employeeDetailsData', 'summary'))->render();

                $options = new \Dompdf\Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true);

                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                $filename = "Salary_Slip_{$empCode}_{$monthName}_{$request->year}.pdf";
                $filePath = $tempDir . '/' . $filename;
                file_put_contents($filePath, $dompdf->output());
                $generatedFiles[] = $filePath;
            }

            $zipFileName = "Salary_Slips_{$request->companyName}_{$monthName}_{$request->year}.zip";
            $zipFilePath = storage_path('app/temp/' . $zipFileName);

            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($generatedFiles as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
            }

            foreach ($generatedFiles as $file) {
                unlink($file);
            }
            rmdir($tempDir);

            return response()->download($zipFilePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error generating bulk salary slips: ' . $e->getMessage());
            abort(500, 'Error generating salary slips: ' . $e->getMessage());
        }
    }

    /**
     * GET route handler for bulk download (delegates to POST handler) - original design
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadAllSalarySlipsPdfGet(Request $request)
    {
        return $this->downloadAllSalarySlipsPdf($request);
    }

    /**
     * Download custom salary slip PDF for a single employee
     *
     * @param string $corpId
     * @param string $empCode
     * @param string $year
     * @param string $month
     * @param string|null $companyName
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadCustomSalarySlipPdf($corpId, $empCode, $year, $month, $companyName = null)
    {
        try {
            // Normalize month: accept numeric (1-12) or month name ("January", etc.)
            if (is_numeric($month)) {
                $monthNumber = (int)$month;
                $monthName = date('F', mktime(0, 0, 0, $monthNumber, 1));
            } else {
                // Month is already a name like "January"
                $monthName = ucfirst(strtolower($month));
                $monthNumber = (int)date('n', strtotime($monthName));
            }

            // Query payroll: the database stores month as string month name
            $query = EmployeePayrollSalaryProcess::where('corpId', $corpId)
                ->where('empCode', $empCode)
                ->where('year', $year)
                ->where('month', $monthName);

            if ($companyName) {
                $query->where('companyName', $companyName);
            }

            $payroll = $query->first();

            if (!$payroll) {
                abort(404, 'Payroll record not found for the specified employee and period.');
            }

            // Fetch related data (these tables use snake_case for corp_id)
            $employeeDetail = \App\Models\EmployeeDetail::where('corp_id', $corpId)->where('EmpCode', $empCode)->first();
            $employmentDetail = \App\Models\EmploymentDetail::where('corp_id', $corpId)->where('EmpCode', $empCode)->first();
            $companyDetails = \App\Models\CompanyDetails::where('corp_id', $corpId)->first();
            $statutoryDetail = \App\Models\EmployeeStatutoryDetail::where('corp_id', $corpId)->where('EmpCode', $empCode)->first();
            $bankDetail = \App\Models\EmployeeBankDetail::where('corp_id', $corpId)->where('empcode', $empCode)->first();
            
            // Attendance table stores mixed month formats across data sets.
            $attendanceSummary = $this->getAttendanceSummaryForPayslip(
                $corpId,
                $empCode,
                $year,
                $monthName,
                $monthNumber
            );

            $salaryStructure = \App\Models\EmployeeSalaryStructure::where('corpId', $corpId)
                ->where('companyName', $payroll->companyName)
                ->where('empCode', $empCode)
                ->where('year', $year)
                ->first();

            // Process payroll data
            $grossList = json_decode($payroll->grossList, true) ?: [];
            $otherAllowances = json_decode($payroll->otherAllowances, true) ?: [];
            $otherBenefits = json_decode($payroll->otherBenefits, true) ?: [];
            $earningsBreakup = $this->buildPayslipEarningsBreakup($grossList, $otherAllowances, $otherBenefits);
            $earnings = $earningsBreakup['rows'];
            $deductions = json_decode($payroll->recurringDeduction, true) ?: [];
            $totalEarnings = (float)($earningsBreakup['totalCombined'] ?? 0);
            $totalDeductions = array_sum(array_column($deductions, 'calculatedValue'));
            $netPay = $totalEarnings - $totalDeductions;
            $attendanceArrearDays = $this->resolveAttendanceArrearDays($attendanceSummary);
            $incrementArrearDays = $this->calculateIncrementArrearDaysForStructure($salaryStructure, $monthName, $year);

            // Prepare data for the view
            $data = [
                'company' => $companyDetails,
                'employee' => $employeeDetail,
                'employment' => $employmentDetail,
                'statutory' => $statutoryDetail,
                'bank' => $bankDetail,
                'payroll' => $payroll,
                'monthName' => $monthName,
                'year' => $year,
                'attendance' => $attendanceSummary,
                'earnings' => $earnings,
                'deductions' => $deductions,
                'totalMonthlyEarnings' => (float)($earningsBreakup['totalMonthly'] ?? 0),
                'totalArrearEarnings' => (float)($earningsBreakup['totalArrear'] ?? 0),
                'totalEarnings' => $totalEarnings,
                'totalDeductions' => $totalDeductions,
                'netPay' => $netPay,
                'netPayInWords' => $this->numberToWords($netPay),
                'payDays' => $attendanceSummary ? $attendanceSummary->paidDays : 30,
                'attendanceArrearDays' => $attendanceArrearDays,
                'incrementArrearDays' => $incrementArrearDays,
            ];

            // Generate PDF
            $html = view('salary-slip-custom-pdf', $data)->render();
            
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = "Payslip_{$empCode}_{$monthName}_{$year}.pdf";

            return response()->streamDownload(function() use ($dompdf) {
                echo $dompdf->output();
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating single custom salary slip: ' . $e->getMessage());
            abort(500, 'Error generating salary slip: ' . $e->getMessage());
        }
    }

    /**
     * Generate and download custom salary slip PDFs for all employees in a ZIP file
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadAllCustomSalarySlipsPdf(Request $request)
    {
        // 1. Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'status' => 'nullable|string',
        ]);

        try {
            // Normalize month: accept numeric (1-12) or month name ("January", etc.)
            if (is_numeric($request->month)) {
                $monthNumber = (int)$request->month;
                $monthName = date('F', mktime(0, 0, 0, $monthNumber, 1));
            } else {
                // Month is already a name like "January"
                $monthName = ucfirst(strtolower($request->month));
                $monthNumber = (int)date('n', strtotime($monthName));
            }

            // 2. Fetch Payroll Records (payroll table uses camelCase: corpId, companyName)
            // The database stores month as string month name ("January", "July", etc.)
            $query = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $monthName);

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            $payrollRecords = $query->get();

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No payroll records found for the specified criteria.');
            }

            // 3. Bulk Fetch All Required Data (other tables use snake_case: corp_id)
            $empCodes = $payrollRecords->pluck('empCode')->unique()->toArray();
            
            $employeeDetails = \App\Models\EmployeeDetail::where('corp_id', $request->corpId)->whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            $employmentDetails = \App\Models\EmploymentDetail::where('corp_id', $request->corpId)->whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            $companyDetails = \App\Models\CompanyDetails::where('corp_id', $request->corpId)->first();
            $statutoryDetails = \App\Models\EmployeeStatutoryDetail::where('corp_id', $request->corpId)->whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            $bankDetails = \App\Models\EmployeeBankDetail::where('corp_id', $request->corpId)->whereIn('empcode', $empCodes)->get()->keyBy('empcode');
            
            $salaryStructures = \App\Models\EmployeeSalaryStructure::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->whereIn('empCode', $empCodes)
                ->get()->keyBy('empCode');

            // Attendance table uses camelCase and stores mixed month formats
            $attendanceSummaries = \App\Models\EmployeeAttendanceSummary::where('corpId', $request->corpId)
                ->where('year', $request->year)
                ->whereIn('month', [$monthName, (string)$monthNumber])
                ->whereIn('empCode', $empCodes)
                ->get()
                ->groupBy('empCode');

            // 4. Prepare for PDF Generation
            $tempDir = storage_path('app/temp/custom_salary_slips_' . time());
            File::makeDirectory($tempDir, 0755, true);
            $generatedFiles = [];

            // 5. Loop and Generate PDFs
            foreach ($payrollRecords as $payroll) {
                $empCode = $payroll->empCode;
                
                // Process Payroll Data
                $grossList = json_decode($payroll->grossList, true) ?: [];
                $otherAllowances = json_decode($payroll->otherAllowances, true) ?: [];
                $otherBenefits = json_decode($payroll->otherBenefits, true) ?: [];
                $earningsBreakup = $this->buildPayslipEarningsBreakup($grossList, $otherAllowances, $otherBenefits);
                $earnings = $earningsBreakup['rows'];
                $deductions = json_decode($payroll->recurringDeduction, true) ?: [];
                $totalEarnings = (float)($earningsBreakup['totalCombined'] ?? 0);
                $totalDeductions = array_sum(array_column($deductions, 'calculatedValue'));
                $netPay = $totalEarnings - $totalDeductions;
                $attendanceGroup = $attendanceSummaries->get($empCode, collect());
                $attendance = $attendanceGroup->firstWhere('month', $monthName) ?? $attendanceGroup->first();
                $salaryStructure = $salaryStructures->get($empCode);
                $attendanceArrearDays = $this->resolveAttendanceArrearDays($attendance);
                $incrementArrearDays = $this->calculateIncrementArrearDaysForStructure($salaryStructure, $monthName, $request->year);

                // Prepare Data for View
                $data = [
                    'company' => $companyDetails,
                    'employee' => $employeeDetails->get($empCode),
                    'employment' => $employmentDetails->get($empCode),
                    'statutory' => $statutoryDetails->get($empCode),
                    'bank' => $bankDetails->get($empCode),
                    'payroll' => $payroll,
                    'monthName' => $monthName,
                    'year' => $request->year,
                    'attendance' => $attendance,
                    'earnings' => $earnings,
                    'deductions' => $deductions,
                    'totalMonthlyEarnings' => (float)($earningsBreakup['totalMonthly'] ?? 0),
                    'totalArrearEarnings' => (float)($earningsBreakup['totalArrear'] ?? 0),
                    'totalEarnings' => $totalEarnings,
                    'totalDeductions' => $totalDeductions,
                    'netPay' => $netPay,
                    'netPayInWords' => $this->numberToWords($netPay),
                    'payDays' => $attendance->paidDays ?? 30,
                    'attendanceArrearDays' => $attendanceArrearDays,
                    'incrementArrearDays' => $incrementArrearDays,
                ];

                // Generate HTML and PDF
                $html = view('salary-slip-custom-pdf', $data)->render();
                
                $options = new \Dompdf\Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true);

                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                $filename = "Payslip_{$empCode}_{$monthName}_{$request->year}.pdf";
                $filePath = $tempDir . '/' . $filename;
                file_put_contents($filePath, $dompdf->output());
                $generatedFiles[] = $filePath;
            }

            // 6. Create ZIP file
            if (empty($generatedFiles)) {
                File::deleteDirectory($tempDir);
                abort(500, "Failed to generate any PDF files.");
            }

            $zipFilename = "Custom_Payslips_{$request->companyName}_{$monthName}_{$request->year}.zip";
            $zipPath = $tempDir . '/' . $zipFilename;

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                foreach ($generatedFiles as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
            } else {
                File::deleteDirectory($tempDir);
                abort(500, 'Failed to create ZIP file.');
            }

            // 7. Return ZIP file for download
            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            if (isset($tempDir) && File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            Log::error('Error generating bulk custom salary slips: ' . $e->getMessage());
            abort(500, 'Error generating bulk custom salary slips: ' . $e->getMessage());
        }
    }

    /**
     * Download all custom salary slips as PDF (GET method for FlutterFlow compatibility)
     */
    public function downloadAllCustomSalarySlipsPdfGet(Request $request)
    {
        return $this->downloadAllCustomSalarySlipsPdf($request);
    }


    /**
     * Initiate salary processing for specific employees using empcode list
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiateSelectedEmployeeSalary(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'empCodes' => 'required|array|min:1',
            'empCodes.*' => 'required|string|max:20',
            'status' => 'required|string',
            'isShownToEmployeeYn' => 'required|integer',
        ]);

        try {
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            $skippedEmployees = [];
            $processedEmployees = [];

            // Check if any payroll already exists for this period and these employees
                // Normalize month to month name for consistency
                $month = $request->month;
                if (is_numeric($month) && $month >= 1 && $month <= 12) {
                    $month = date('F', mktime(0, 0, 0, (int)$month, 1));
                }

                $existingPayrollQuery = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                    ->where('companyName', $request->companyName)
                    ->where('year', $request->year)
                    ->where('month', $month)
                    ->whereIn('empCode', $request->empCodes);

            $existingPayrolls = $existingPayrollQuery->get();

            if ($existingPayrolls->isNotEmpty()) {
                $existingEmpCodes = $existingPayrolls->pluck('empCode')->toArray();
                return response()->json([
                    'status' => false,
                    'message' => 'Payroll for some employees has already been initiated for this period',
                    'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}",
                    'existing_employees' => $existingEmpCodes,
                    'existing_records_count' => $existingPayrolls->count(),
                    'duplicate_prevention' => true
                ], 409);
            }

            // Get salary structures for the specified employees - MUST filter by year
            $salaryStructures = \App\Models\EmployeeSalaryStructure::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->whereIn('empCode', $request->empCodes)
                ->get();

            if ($salaryStructures->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No salary structures found for the specified employees',
                    'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}",
                    'requested_employees' => $request->empCodes,
                    'found_employees' => []
                ], 404);
            }

            // Get list of employees found in salary structures
            $foundEmpCodes = $salaryStructures->pluck('empCode')->toArray();
            $notFoundEmpCodes = array_diff($request->empCodes, $foundEmpCodes);

            // Process each salary structure
            foreach ($salaryStructures as $structure) {
                try {
                    $arrearsContext = $this->calculateArrearsContextForStructure(
                        $structure,
                        $month,
                        $request->year
                    );

                    $grossList = $this->safeJsonDecode($structure->grossList);
                    $grossList = $this->appendArrearsComponentToGross(
                        $grossList,
                        $arrearsContext['arrearsAmount']
                    );

                    // Create payroll entry
                    $payrollData = [
                        'corpId' => $structure->corpId,
                        'empCode' => $structure->empCode,
                        'companyName' => $structure->companyName,
                        'year' => $request->year,
                        'month' => $month, // Use normalized month name
                        'grossList' => json_encode($grossList),
                        'otherAllowances' => $structure->otherAlowances,
                        'otherBenefits' => $structure->otherBenifits,
                        'recurringDeduction' => $structure->recurringDeductions,
                        'status' => $request->status,
                        'isShownToEmployeeYn' => $request->isShownToEmployeeYn,
                    ];

                    EmployeePayrollSalaryProcess::create($payrollData);

                    $processedEmployees[] = $structure->empCode;
                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'empCode' => $structure->empCode,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Add not found employees to skipped list
            foreach ($notFoundEmpCodes as $empCode) {
                $skippedEmployees[] = [
                    'empCode' => $empCode,
                    'reason' => 'Salary structure not found'
                ];
            }

            $filterDescription = "corpId: {$request->corpId}, companyName: {$request->companyName}";

            $response = [
                'status' => true,
                'message' => "Successfully initiated salary for {$successCount} employees",
                'filter' => $filterDescription,
                'summary' => [
                    'requested_employees' => count($request->empCodes),
                    'salary_structures_found' => $salaryStructures->count(),
                    'successfully_processed' => $successCount,
                    'processing_errors' => $errorCount,
                    'employees_not_found' => count($notFoundEmpCodes)
                ],
                'processed_employees' => $processedEmployees,
                'skipped_employees' => $skippedEmployees,
                'processing_errors' => $errors
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error initiating selected employee salary: ' . $e->getMessage(),
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}"
            ], 500);
        }
    }

    /**
     * Get employee details with salary status for a specific period
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmployeeDetailsWithStatus(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
        ]);

        try {
            // Get all employee details with employment details for the company
            $employeeQuery = EmployeeDetail::where('employee_details.corp_id', $request->corpId)
                ->join('employment_details', function($join) use ($request) {
                    $join->on('employee_details.EmpCode', '=', 'employment_details.EmpCode')
                         ->where('employment_details.corp_id', $request->corpId)
                         ->where('employment_details.company_name', $request->companyName);
                })
                ->select(
                    'employee_details.EmpCode',
                    'employee_details.FirstName',
                    'employee_details.MiddleName',
                    'employee_details.LastName',
                    'employee_details.Mobile',
                    'employee_details.WorkEmail',
                    'employee_details.PersonalEmail',
                    'employment_details.company_name',
                    'employment_details.Designation',
                    'employment_details.Department'
                );

            $employees = $employeeQuery->get();

            if ($employees->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No employees found for the specified company',
                    'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}",
                    'data' => []
                ]);
            }

            // Normalize month to month name for consistency
            $month = $request->month;
            if (is_numeric($month) && $month >= 1 && $month <= 12) {
                $month = date('F', mktime(0, 0, 0, (int)$month, 1));
            }

            // Get payroll status for all employees for the specified period
            $empCodes = $employees->pluck('EmpCode')->toArray();
            $payrollStatuses = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month)
                ->whereIn('empCode', $empCodes)
                ->get(['empCode', 'status', 'grossList', 'otherAllowances'])
                ->keyBy('empCode')
                ->toArray();

            $salaryStructures = EmployeeSalaryStructure::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->whereIn('empCode', $empCodes)
                ->get(['empCode', 'salaryRevisionMonth', 'arrearWithEffectFrom', 'increment'])
                ->keyBy('empCode');

            // Format employee details with status
            $formattedEmployees = [];
            $serialNo = 1;

            foreach ($employees as $employee) {
                // Build full name
                $firstName = $employee->FirstName ?? '';
                $middleName = $employee->MiddleName ?? '';
                $lastName = $employee->LastName ?? '';
                
                $fullName = $firstName;
                if (!empty($middleName)) {
                    $fullName .= ' ' . $middleName;
                }
                if (!empty($lastName)) {
                    $fullName .= ' ' . $lastName;
                }

                // Determine status
                $status = 'Not Initiated';
                $payrollRecord = $payrollStatuses[$employee->EmpCode] ?? null;
                if ($payrollRecord && isset($payrollRecord['status'])) {
                    $payrollStatus = $payrollRecord['status'];
                    switch ($payrollStatus) {
                        case 'Initiated':
                            $status = 'Initiated';
                            break;
                        case 'Increment Released':
                            $status = 'Increment Released';
                            break;
                        case 'On Hold':
                            $status = 'On Hold';
                            break;
                        case 'Released':
                            $status = 'Released';
                            break;
                        case 'Pending':
                            $status = 'Pending';
                            break;
                        default:
                            $status = $payrollStatus; // Use actual status if it's different
                    }
                }

                $arrearsAmount = 0;
                if ($payrollRecord) {
                    $grossList = $this->safeJsonDecode($payrollRecord['grossList'] ?? '[]');
                    foreach ($grossList as $gross) {
                        if (strtolower((string)($gross['componentName'] ?? '')) === 'arrears') {
                            $arrearsAmount = (float)($gross['calculatedValue'] ?? 0);
                            break;
                        }
                    }

                    if ($arrearsAmount <= 0 && !empty($payrollRecord['otherAllowances'])) {
                        $otherAllowances = $this->safeJsonDecode($payrollRecord['otherAllowances']);
                        foreach ($otherAllowances as $allowance) {
                            if (strtolower((string)($allowance['componentName'] ?? '')) === 'arrears') {
                                $arrearsAmount = (float)($allowance['calculatedValue'] ?? 0);
                                break;
                            }
                        }
                    }

                    if (
                        $arrearsAmount <= 0 &&
                        (($payrollRecord['status'] ?? '') === 'Increment Released') &&
                        $salaryStructures->has($employee->EmpCode)
                    ) {
                        $structure = $salaryStructures->get($employee->EmpCode);
                        $arrearsContext = $this->calculateArrearsContextForStructure(
                            $structure,
                            $month,
                            $request->year
                        );
                        $arrearsAmount = (float)($arrearsContext['arrearsAmount'] ?? 0);
                    }
                }

                $hasRevisionWithArrears = false;
                if ($salaryStructures->has($employee->EmpCode)) {
                    $structure = $salaryStructures->get($employee->EmpCode);
                    $hasRevisionWithArrears =
                        !empty($structure->salaryRevisionMonth) &&
                        !empty($structure->arrearWithEffectFrom) &&
                        ((float)($structure->increment ?? 0) > 0);
                }

                $formattedEmployees[] = [
                    'serial_no' => $serialNo++,
                    'empcode' => $employee->EmpCode,
                    'employee_full_name' => trim($fullName) ?: 'N/A',
                    'mobile_number' => $employee->Mobile ?? 'N/A',
                    'work_email' => $employee->WorkEmail ?? 'N/A',
                    'personal_email' => $employee->PersonalEmail ?? 'N/A',
                    'designation' => $employee->Designation ?? 'N/A',
                    'department' => $employee->Department ?? 'N/A',
                    'current_year' => $request->year,
                    'current_month' => $request->month,
                    'status' => $status,
                    'arrears_amount' => round($arrearsAmount, 2),
                    'has_revision_with_arrears' => $hasRevisionWithArrears
                ];
            }

            // Get status summary
            $statusSummary = [];
            foreach ($formattedEmployees as $emp) {
                $status = $emp['status'];
                $statusSummary[$status] = ($statusSummary[$status] ?? 0) + 1;
            }

            return response()->json([
                'status' => true,
                'message' => 'Employee details retrieved successfully',
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}",
                'summary' => [
                    'total_employees' => count($formattedEmployees),
                    'status_breakdown' => $statusSummary
                ],
                'data' => $formattedEmployees
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving employee details: ' . $e->getMessage(),
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}",
                'data' => []
            ], 500);
        }
    }

    /**
     * Determine whether arrears-driven release options should be visible.
     */
    public function getReleaseOptionsAvailability($corpId, $companyName, $year, $month)
    {
        try {
            $monthName = $this->normalizeMonthName($month);

            $payrollRecords = EmployeePayrollSalaryProcess::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('year', $year)
                ->where('month', $monthName)
                ->get(['empCode', 'status', 'month', 'year']);

            if ($payrollRecords->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'showIncrementedWithArrearsButton' => false,
                        'showIncrementedOnlyButton' => false,
                        'showPreviousSalaryButton' => true,
                        'employeesWithRevisionArrears' => 0
                    ]
                ]);
            }

            $empCodes = $payrollRecords->pluck('empCode')->unique()->values()->toArray();

            $salaryStructures = EmployeeSalaryStructure::where('corpId', $corpId)
                ->where('companyName', $companyName)
                ->where('year', $year)
                ->whereIn('empCode', $empCodes)
                ->get()
                ->keyBy('empCode');

            $showIncrementedWithArrearsButton = false;
            $showIncrementedOnlyButton = false;
            $showPreviousSalaryButton = false;
            $employeesWithRevisionArrears = [];

            foreach ($payrollRecords as $record) {
                $status = strtolower((string)$record->status);
                $isReleased = $status === 'released';
                $isInitiated = $status === 'initiated';

                if ($isInitiated) {
                    $showPreviousSalaryButton = true;
                }

                $structure = $salaryStructures->get($record->empCode);
                if (!$structure) {
                    continue;
                }

                $hasRevisionWithArrears =
                    !empty($structure->salaryRevisionMonth) &&
                    !empty($structure->arrearWithEffectFrom) &&
                    ((float)($structure->increment ?? 0) > 0);

                if ($isInitiated && $hasRevisionWithArrears) {
                    $showIncrementedOnlyButton = true;
                }

                $arrearsContext = $this->calculateArrearsContextForStructure(
                    $structure,
                    $monthName,
                    $year
                );
                $arrearsAmount = (float)($arrearsContext['arrearsAmount'] ?? 0);

                if (!$isReleased && $arrearsAmount > 0) {
                    $showIncrementedWithArrearsButton = true;
                    $employeesWithRevisionArrears[$record->empCode] = true;
                }
            }

            $employeesWithRevisionArrearsCount = count($employeesWithRevisionArrears);

            return response()->json([
                'status' => true,
                'data' => [
                    'showIncrementedWithArrearsButton' => $showIncrementedWithArrearsButton,
                    'showIncrementedOnlyButton' => $showIncrementedOnlyButton,
                    'showPreviousSalaryButton' => $showPreviousSalaryButton,
                    'employeesWithRevisionArrears' => $employeesWithRevisionArrearsCount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error checking release options: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee salary status summary with detailed breakdown
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmployeeSalarySummary(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
        ]);

        try {
            // Get all employee details with employment details for the company
            $employeeQuery = EmployeeDetail::where('employee_details.corp_id', $request->corpId)
                ->join('employment_details', function($join) use ($request) {
                    $join->on('employee_details.EmpCode', '=', 'employment_details.EmpCode')
                         ->where('employment_details.corp_id', $request->corpId)
                         ->where('employment_details.company_name', $request->companyName);
                })
                ->select(
                    'employee_details.EmpCode',
                    'employee_details.FirstName',
                    'employee_details.MiddleName',
                    'employee_details.LastName'
                );

            $employees = $employeeQuery->get();

            if ($employees->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No employees found for the specified company',
                    'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}",
                    'summary' => [
                        'total_employees' => 0,
                        'total_initiated' => 0,
                        'total_not_initiated' => 0,
                        'total_released' => 0,
                        'total_on_hold' => 0,
                        'total_pending' => 0
                    ],
                    'data' => []
                ]);
            }

            // Normalize month for queries
            $monthNumber = is_numeric($request->month) ? (int)$request->month : (int)date('n', strtotime($request->month));

            // Get payroll records for the specified period
            $empCodes = $employees->pluck('EmpCode')->toArray();
            $payrollRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $monthNumber)
                ->whereIn('empCode', $empCodes)
                ->get();

            // Create status mapping
            $payrollStatuses = $payrollRecords->pluck('status', 'empCode')->toArray();

            // Initialize counters and arrays
            $totalEmployees = $employees->count();
            $statusCounts = [
                'Initiated' => 0,
                'Not Initiated' => 0,
                'Released' => 0,
                'On Hold' => 0,
                'Pending' => 0
            ];

            $employeesByStatus = [
                'initiated_employees' => [],
                'not_initiated_employees' => [],
                'released_employees' => [],
                'on_hold_employees' => [],
                'pending_employees' => []
            ];

            // Process each employee
            foreach ($employees as $employee) {
                // Build full name
                $firstName = $employee->FirstName ?? '';
                $middleName = $employee->MiddleName ?? '';
                $lastName = $employee->LastName ?? '';
                
                $fullName = $firstName;
                if (!empty($middleName)) {
                    $fullName .= ' ' . $middleName;
                }
                if (!empty($lastName)) {
                    $fullName .= ' ' . $lastName;
                }

                $employeeInfo = [
                    'empcode' => $employee->EmpCode,
                    'employee_full_name' => trim($fullName) ?: 'N/A'
                ];

                // Determine status and categorize
                if (isset($payrollStatuses[$employee->EmpCode])) {
                    $payrollStatus = $payrollStatuses[$employee->EmpCode];
                    switch ($payrollStatus) {
                        case 'Initiated':
                            $statusCounts['Initiated']++;
                            $employeesByStatus['initiated_employees'][] = $employeeInfo;
                            break;
                        case 'Released':
                            $statusCounts['Released']++;
                            $employeesByStatus['released_employees'][] = $employeeInfo;
                            break;
                        case 'On Hold':
                            $statusCounts['On Hold']++;
                            $employeesByStatus['on_hold_employees'][] = $employeeInfo;
                            break;
                        case 'Pending':
                            $statusCounts['Pending']++;
                            $employeesByStatus['pending_employees'][] = $employeeInfo;
                            break;
                        default:
                            // Any other status treated as initiated
                            $statusCounts['Initiated']++;
                            $employeesByStatus['initiated_employees'][] = $employeeInfo;
                            break;
                    }
                } else {
                    // No payroll record found
                    $statusCounts['Not Initiated']++;
                    $employeesByStatus['not_initiated_employees'][] = $employeeInfo;
                }
            }

            // Extract empcode arrays for easy access
            $empcodesByStatus = [
                'initiated_empcodes' => collect($employeesByStatus['initiated_employees'])->pluck('empcode')->toArray(),
                'not_initiated_empcodes' => collect($employeesByStatus['not_initiated_employees'])->pluck('empcode')->toArray(),
                'released_empcodes' => collect($employeesByStatus['released_employees'])->pluck('empcode')->toArray(),
                'on_hold_empcodes' => collect($employeesByStatus['on_hold_employees'])->pluck('empcode')->toArray(),
                'pending_empcodes' => collect($employeesByStatus['pending_employees'])->pluck('empcode')->toArray()
            ];

            return response()->json([
                'status' => true,
                'message' => 'Employee salary summary retrieved successfully',
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$request->month}",
                'summary' => [
                    'total_employees' => $totalEmployees,
                    'total_initiated' => $statusCounts['Initiated'],
                    'total_not_initiated' => $statusCounts['Not Initiated'],
                    'total_released' => $statusCounts['Released'],
                    'total_on_hold' => $statusCounts['On Hold'],
                    'total_pending' => $statusCounts['Pending'],
                    'percentage_completed' => $totalEmployees > 0 ? round((($statusCounts['Initiated'] + $statusCounts['Released']) / $totalEmployees) * 100, 2) : 0
                ],
                'employee_details' => $employeesByStatus,
                'empcode_lists' => $empcodesByStatus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving employee salary summary: ' . $e->getMessage(),
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}",
                'summary' => [
                    'total_employees' => 0,
                    'total_initiated' => 0,
                    'total_not_initiated' => 0,
                    'total_released' => 0,
                    'total_on_hold' => 0,
                    'total_pending' => 0
                ],
                'data' => []
            ], 500);
        }
    }

    /**
     * Get detailed payroll information with branch details (JSON version of export)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailedPayrollWithBranches(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'nullable|string|max:4',
            'month' => 'nullable|string|max:50',
            'branch' => 'nullable|string|max:100',
            'subBranch' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:Initiated,Released,On Hold,Pending',
        ]);

        try {
            // Start building the payroll query
            $payrollQuery = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName);

            // Add optional filters
            if ($request->has('year') && !empty($request->year)) {
                $payrollQuery->where('year', $request->year);
            }

            if ($request->has('month') && !empty($request->month)) {
                //$monthNumber = is_numeric($request->month) ? (int)$request->month : (int)date('n', strtotime($request->month));
                //$payrollQuery->where('month', $monthNumber);
                 $payrollQuery->where('month', $request->month);
            }

            if ($request->has('status') && !empty($request->status)) {
                $payrollQuery->where('status', $request->status);
            }

            $payrollRecords = $payrollQuery->get();

            if ($payrollRecords->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No payroll records found for the specified criteria',
                    'filter' => $this->buildFilterDescription($request),
                    'data' => [],
                    'summary' => [
                        'total_records' => 0,
                        'total_branches' => 0,
                        'total_employees' => 0
                    ]
                ]);
            }

            // Get all employee codes from payroll records
            $empCodes = $payrollRecords->pluck('empCode')->unique()->toArray();
            
            // Fetch employee details
            $employeeDetails = EmployeeDetail::whereIn('EmpCode', $empCodes)
                ->where('corp_id', $request->corpId)
                ->get()
                ->keyBy('EmpCode');
            
            // Build employment details query with branch filters
            $employmentDetailsQuery = EmploymentDetail::whereIn('EmpCode', $empCodes)
                ->where('corp_id', $request->corpId)
                ->where('company_name', $request->companyName);
            
            // Apply branch filters if provided
            if ($request->has('branch') && !empty($request->branch)) {
                $employmentDetailsQuery->where('Branch', $request->branch);
            }
            
            if ($request->has('subBranch') && !empty($request->subBranch)) {
                $employmentDetailsQuery->where('SubBranch', $request->subBranch);
            }
            
            $employmentDetails = $employmentDetailsQuery->get()->keyBy('EmpCode');

            // Filter payroll records based on employment details (if branch filters applied)
            if (($request->has('branch') && !empty($request->branch)) || 
                ($request->has('subBranch') && !empty($request->subBranch))) {
                $filteredEmpCodes = $employmentDetails->pluck('EmpCode')->toArray();
                $payrollRecords = $payrollRecords->whereIn('empCode', $filteredEmpCodes);
            }

            if ($payrollRecords->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No payroll records found after applying branch filters',
                    'filter' => $this->buildFilterDescription($request),
                    'data' => [],
                    'summary' => [
                        'total_records' => 0,
                        'total_branches' => 0,
                        'total_employees' => 0
                    ]
                ]);
            }

            $detailedPayrollData = [];
            $dynamicComponents = [];
            $branchSummary = [];
            $totalAmounts = [
                'total_gross' => 0,
                'total_benefits' => 0,
                'total_deductions' => 0,
                'total_net_salary' => 0
            ];

            // First pass: collect all dynamic component names
            foreach ($payrollRecords as $record) {
                $grossList = $this->safeJsonDecode($record->grossList);
                $otherAllowances = $this->safeJsonDecode($record->otherAllowances);
                $otherBenefits = $this->safeJsonDecode($record->otherBenefits);
                $recurringDeductions = $this->safeJsonDecode($record->recurringDeduction);

                foreach ($grossList as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $dynamicComponents['gross'][$componentName] = true;
                }
                foreach ($otherAllowances as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $dynamicComponents['allowances'][$componentName] = true;
                }
                foreach ($otherBenefits as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $dynamicComponents['benefits'][$componentName] = true;
                }
                foreach ($recurringDeductions as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $dynamicComponents['deductions'][$componentName] = true;
                }
            }

            // Second pass: process each payroll record
            $serialNo = 1;
            foreach ($payrollRecords as $record) {
                $employeeDetail = $employeeDetails->get($record->empCode);
                $employmentDetail = $employmentDetails->get($record->empCode);

                if (!$employmentDetail) {
                    continue; // Skip if employment detail not found
                }

                // Get full name
                $fullName = $this->getFullEmployeeName($employeeDetail);

                // Parse JSON fields
                $grossList = $this->safeJsonDecode($record->grossList);
                $otherAllowances = $this->safeJsonDecode($record->otherAllowances);
                $otherBenefits = $this->safeJsonDecode($record->otherBenefits);
                $recurringDeductions = $this->safeJsonDecode($record->recurringDeduction);

                // Calculate salary components
                $salaryBreakdown = $this->calculateDetailedSalaryBreakdown(
                    $grossList, 
                    $otherAllowances, 
                    $otherBenefits, 
                    $recurringDeductions,
                    $dynamicComponents
                );

                // Build branch summary
                $branch = $employmentDetail->Branch ?? 'N/A';
                $subBranch = $employmentDetail->SubBranch ?? 'N/A';
                $branchKey = $branch . '|' . $subBranch;
                
                if (!isset($branchSummary[$branchKey])) {
                    $branchSummary[$branchKey] = [
                        'branch' => $branch,
                        'sub_branch' => $subBranch,
                        'employee_count' => 0,
                        'total_gross' => 0,
                        'total_net_salary' => 0
                    ];
                }
                $branchSummary[$branchKey]['employee_count']++;
                $branchSummary[$branchKey]['total_gross'] += $salaryBreakdown['monthly_gross_total'];
                $branchSummary[$branchKey]['total_net_salary'] += $salaryBreakdown['monthly_net_salary'];

                // Add to overall totals
                $totalAmounts['total_gross'] += $salaryBreakdown['monthly_gross_total'];
                $totalAmounts['total_benefits'] += $salaryBreakdown['monthly_benefits_total'];
                $totalAmounts['total_deductions'] += $salaryBreakdown['monthly_deductions_total'];
                $totalAmounts['total_net_salary'] += $salaryBreakdown['monthly_net_salary'];

                // Build detailed record
                $detailedRecord = [
                    'serial_no' => $serialNo++,
                    'employee_details' => [
                        'empcode' => $record->empCode,
                        'employee_full_name' => $fullName ?: 'N/A',
                        'mobile_number' => $employeeDetail->Mobile ?? 'N/A',
                        'work_email' => $employeeDetail->WorkEmail ?? 'N/A',
                        'designation' => $employmentDetail->Designation ?? 'N/A',
                        'department' => $employmentDetail->Department ?? 'N/A',
                        'date_of_joining' => $employmentDetail->dateOfJoining ?? 'N/A',
                        'branch' => $branch,
                        'sub_branch' => $subBranch,
                        'region' => $employmentDetail->Region ?? 'N/A',
                        'business_unit' => $employmentDetail->BusinessUnit ?? 'N/A'
                    ],
                    'payroll_details' => [
                        'corp_id' => $record->corpId,
                        'company_name' => $record->companyName,
                        'year' => $record->year,
                        'month' => $record->month,
                        'status' => $record->status,
                        'is_shown_to_employee' => $record->isShownToEmployeeYn
                    ],
                    'salary_breakdown' => $salaryBreakdown,
                    'salary_summary' => [
                        'monthly_gross_total' => round($salaryBreakdown['monthly_gross_total'], 2),
                        'monthly_benefits_total' => round($salaryBreakdown['monthly_benefits_total'], 2),
                        'monthly_deductions_total' => round($salaryBreakdown['monthly_deductions_total'], 2),
                        'monthly_net_salary' => round($salaryBreakdown['monthly_net_salary'], 2),
                        'annual_gross_total' => round($salaryBreakdown['monthly_gross_total'] * 12, 2),
                        'annual_net_salary' => round($salaryBreakdown['monthly_net_salary'] * 12, 2)
                    ]
                ];

                $detailedPayrollData[] = $detailedRecord;
            }

            // Prepare branch list for filtering
            $branchList = collect($branchSummary)->map(function($summary) {
                return [
                    'branch' => $summary['branch'],
                    'sub_branch' => $summary['sub_branch'],
                    'employee_count' => $summary['employee_count'],
                    'total_gross' => round($summary['total_gross'], 2),
                    'total_net_salary' => round($summary['total_net_salary'], 2)
                ];
            })->values()->toArray();

            return response()->json([
                'status' => true,
                'message' => 'Detailed payroll information retrieved successfully',
                'filter' => $this->buildFilterDescription($request),
                'summary' => [
                    'total_records' => count($detailedPayrollData),
                    'total_branches' => count($branchSummary),
                    'total_employees' => count($detailedPayrollData),
                    'total_gross_amount' => round($totalAmounts['total_gross'], 2),
                    'total_benefits_amount' => round($totalAmounts['total_benefits'], 2),
                    'total_deductions_amount' => round($totalAmounts['total_deductions'], 2),
                    'total_net_salary_amount' => round($totalAmounts['total_net_salary'], 2)
                ],
                'branch_summary' => $branchList,
                'dynamic_components' => [
                    'gross_components' => array_keys($dynamicComponents['gross'] ?? []),
                    'allowance_components' => array_keys($dynamicComponents['allowances'] ?? []),
                    'benefit_components' => array_keys($dynamicComponents['benefits'] ?? []),
                    'deduction_components' => array_keys($dynamicComponents['deductions'] ?? [])
                ],
                'data' => $detailedPayrollData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving detailed payroll information: ' . $e->getMessage(),
                'filter' => $this->buildFilterDescription($request),
                'data' => []
            ], 500);
        }
    }

    /**
     * Helper method to build filter description string
     */
    private function buildFilterDescription($request)
    {
        $filters = [];
        $filters[] = "corpId: {$request->corpId}";
        $filters[] = "companyName: {$request->companyName}";
        
        if ($request->has('year') && !empty($request->year)) {
            $filters[] = "year: {$request->year}";
        }
        if ($request->has('month') && !empty($request->month)) {
            $filters[] = "month: {$request->month}";
        }
        if ($request->has('branch') && !empty($request->branch)) {
            $filters[] = "branch: {$request->branch}";
        }
        if ($request->has('subBranch') && !empty($request->subBranch)) {
            $filters[] = "subBranch: {$request->subBranch}";
        }
        if ($request->has('status') && !empty($request->status)) {
            $filters[] = "status: {$request->status}";
        }
        
        return implode(', ', $filters);
    }

    /**
     * Helper method to calculate detailed salary breakdown with dynamic components
     */
    private function calculateDetailedSalaryBreakdown($grossList, $otherAllowances, $otherBenefits, $recurringDeductions, $dynamicComponents)
    {
        $breakdown = [
            'gross_components' => [],
            'allowance_components' => [],
            'benefit_components' => [],
            'deduction_components' => [],
            'monthly_gross_total' => 0,
            'monthly_benefits_total' => 0,
            'monthly_deductions_total' => 0,
            'monthly_net_salary' => 0
        ];

        // Initialize all possible components with 0
        foreach ($dynamicComponents['gross'] ?? [] as $componentName => $true) {
            $breakdown['gross_components'][$componentName] = 0;
        }
        foreach ($dynamicComponents['allowances'] ?? [] as $componentName => $true) {
            $breakdown['allowance_components'][$componentName] = 0;
        }
        foreach ($dynamicComponents['benefits'] ?? [] as $componentName => $true) {
            $breakdown['benefit_components'][$componentName] = 0;
        }
        foreach ($dynamicComponents['deductions'] ?? [] as $componentName => $true) {
            $breakdown['deduction_components'][$componentName] = 0;
        }

        // Process gross components
        foreach ($grossList as $item) {
            $componentName = $item['componentName'] ?? 'Unknown Component';
            $value = (float)($item['calculatedValue'] ?? 0);
            $breakdown['gross_components'][$componentName] = $value;
            $breakdown['monthly_gross_total'] += $value;
        }

        // Process allowance components
        foreach ($otherAllowances as $item) {
            $componentName = $item['componentName'] ?? 'Unknown Component';
            $value = (float)($item['calculatedValue'] ?? 0);
            $breakdown['allowance_components'][$componentName] = $value;
            $breakdown['monthly_benefits_total'] += $value;
        }

        // Process benefit components
        foreach ($otherBenefits as $item) {
            $componentName = $item['componentName'] ?? 'Unknown Component';
            $value = (float)($item['calculatedValue'] ?? 0);
            $breakdown['benefit_components'][$componentName] = $value;
            $breakdown['monthly_benefits_total'] += $value;
        }

        // Process deduction components
        foreach ($recurringDeductions as $item) {
            $componentName = $item['componentName'] ?? 'Unknown Component';
            $value = (float)($item['calculatedValue'] ?? 0);
            $breakdown['deduction_components'][$componentName] = $value;
            $breakdown['monthly_deductions_total'] += $value;
        }

        // Calculate net salary
        $breakdown['monthly_net_salary'] = $breakdown['monthly_gross_total'] + $breakdown['monthly_benefits_total'] - $breakdown['monthly_deductions_total'];

        return $breakdown;
    }

    /**
     * Export payroll data with arrears calculation
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportPayrollWithArrears(Request $request)
    {
        // Validate required fields
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'subBranch' => 'nullable|string|max:100',
 // Optional SubBranch filter
        ]);

        try {
            // Normalize month for queries
            $monthNumber = is_numeric($request->month) ? (int)$request->month : (int)date('n', strtotime($request->month));

            // Get payroll records with Released status - ensure strict year filtering
            $payrollRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $monthNumber)
                ->where('status', 'Released')
                ->whereNotNull('year')
                ->where('year', '!=', '')
                ->get();

            // Log the query results for debugging
            Log::info("ExportPayrollWithArrears query results", [
                'corpId' => $request->corpId,
                'companyName' => $request->companyName,
                'year' => $request->year,
                'month' => $request->month,
                'record_count' => $payrollRecords->count(),
                'years_found' => $payrollRecords->pluck('year')->unique()->toArray(),
                'sample_records' => $payrollRecords->take(3)->map(function($r) {
                    return ['empCode' => $r->empCode, 'year' => $r->year, 'month' => $r->month];
                })->toArray()
            ]);

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No released payroll records found for the specified period');
            }

            // Get all employee codes
            $empCodes = $payrollRecords->pluck('empCode')->unique()->toArray();
            
            // Fetch employee details
            $employeeDetails = EmployeeDetail::whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            
            $employmentDetailsQuery = EmploymentDetail::whereIn('EmpCode', $empCodes);
            
            // Apply SubBranch filter if provided
            if ($request->has('subBranch') && !empty($request->subBranch)) {
                $employmentDetailsQuery->where('SubBranch', $request->subBranch);
            }
            
            $employmentDetails = $employmentDetailsQuery->get()->keyBy('EmpCode');

            // Filter payroll records based on employment details
            if ($request->has('subBranch') && !empty($request->subBranch)) {
                $filteredEmpCodes = $employmentDetails->pluck('EmpCode')->toArray();
                $payrollRecords = $payrollRecords->whereIn('empCode', $filteredEmpCodes);
            }

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No released payroll records found for the specified SubBranch and period');
            }

            // Fetch salary structures with arrears info - ensure year filtering
            $salaryStructures = \App\Models\EmployeeSalaryStructure::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->whereIn('empCode', $empCodes)
                ->whereNotNull('year')
                ->where('year', '!=', '')
                ->get()
                ->keyBy('empCode');

            // Fetch attendance summaries for paid days
            // Normalize month to numeric value for attendance table lookups
            $monthNumber = is_numeric($request->month) ? (int)$request->month : (int)date('n', strtotime($request->month));

            $attendanceSummaries = \App\Models\EmployeeAttendanceSummary::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('month', $monthNumber)
                ->where('year', $request->year)
                ->whereIn('empCode', $empCodes)
                ->get()
                ->keyBy('empCode');

            $excelData = [];
            $dynamicHeaders = ['gross' => [], 'deductions' => []];
            $totals = [];
            $serialNo = 1;
            
            $arrearsStats = [
                'totalEmployees' => 0,
                'employeesWithArrears' => 0,
                'employeesWithoutArrears' => 0,
                'employeesWithoutRevision' => 0,
                'employeesWithArrearsDetails' => [],
                'employeesWithoutRevisionDetails' => []
            ];

            // FIRST PASS: Collect ALL dynamic headers
            foreach ($payrollRecords as $record) {
                $grossList = $this->safeJsonDecode($record->grossList);
                $recurringDeductions = $this->safeJsonDecode($record->recurringDeduction);

                foreach ($grossList as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'gross_' . str_replace(' ', '_', strtolower($componentName));
                    $dynamicHeaders['gross'][$headerKey] = $componentName;
                }
                
                foreach ($recurringDeductions as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'deduction_' . str_replace(' ', '_', strtolower($componentName));
                    $dynamicHeaders['deductions'][$headerKey] = $componentName;
                }
            }

            // SECOND PASS: Process each record with arrears calculation
            foreach ($payrollRecords as $record) {
                $arrearsStats['totalEmployees']++;
                
                $employeeDetail = $employeeDetails->get($record->empCode);
                $employmentDetail = $employmentDetails->get($record->empCode);
                $salaryStructure = $salaryStructures->get($record->empCode);
                
                $fullName = $this->getFullEmployeeName($employeeDetail);

                // Parse current month salary
                $grossList = $this->safeJsonDecode($record->grossList);
                $recurringDeductions = $this->safeJsonDecode($record->recurringDeduction);

                // Calculate current month totals
                $monthlyTotalGross = 0;
                $monthlyTotalDeductions = 0;

                foreach ($grossList as $item) {
                    if (isset($item['calculatedValue']) && is_numeric($item['calculatedValue'])) {
                        $monthlyTotalGross += (float)$item['calculatedValue'];
                    }
                }

                foreach ($recurringDeductions as $item) {
                    if (isset($item['calculatedValue']) && is_numeric($item['calculatedValue'])) {
                        $monthlyTotalDeductions += (float)$item['calculatedValue'];
                    }
                }

                $netTakeHomeMonthly = $monthlyTotalGross - $monthlyTotalDeductions;

                // Calculate arrears
                $arrearStatus = 'No Revision';
                $arrearsEffectiveFrom = '';
                $arrearsMonthCount = 0;
                $totalGrossArrears = 0;
                $totalDeductionArrears = 0;
                $netArrearsPayable = 0;
                $arrearsGrossBreakup = [];
                $arrearsDeductionBreakup = [];

                if ($salaryStructure && !empty($salaryStructure->arrearWithEffectFrom)) {
                    try {
                        // Try to parse different date formats
                        $dateString = $salaryStructure->arrearWithEffectFrom;
                        if (strpos($dateString, '/') !== false) {
                            // Format: 20/8/2025 or 20/08/2025
                            $effectiveFrom = \Carbon\Carbon::createFromFormat('d/m/Y', $dateString);
                        } else {
                            $effectiveFrom = \Carbon\Carbon::parse($dateString);
                        }
                    } catch (\Exception $e) {
                        // If parsing fails, skip arrears calculation
                        $effectiveFrom = null;
                    }
                    
                    if ($effectiveFrom) {
                        $currentMonth = \Carbon\Carbon::parse($request->year . '-' . $request->month . '-01');
                    } else {
                        // Skip arrears if date is invalid
                        $arrearsStats['employeesWithoutRevision']++;
                        $arrearsStats['employeesWithoutRevisionDetails'][] = [
                            'empCode' => $record->empCode,
                            'empName' => $fullName
                        ];
                        
                        // Continue to next record
                        $row['monthlyTotalGross'] = round($monthlyTotalGross, 2);
                        $row['monthlyTotalDeductions'] = round($monthlyTotalDeductions, 2);
                        
                        // Initialize ALL dynamic headers with 0
                        foreach ($dynamicHeaders['gross'] as $headerKey => $headerName) {
                            $row[$headerKey] = 0;
                            $row['arrears_' . $headerKey] = 0;
                        }
                        foreach ($dynamicHeaders['deductions'] as $headerKey => $headerName) {
                            $row[$headerKey] = 0;
                            $row['arrears_' . $headerKey] = 0;
                        }
                        
                        foreach ($grossList as $item) {
                            $componentName = $item['componentName'] ?? 'Unknown Component';
                            $headerKey = 'gross_' . str_replace(' ', '_', strtolower($componentName));
                            $value = (float)($item['calculatedValue'] ?? 0);
                            $row[$headerKey] = $value;
                        }
                        
                        foreach ($recurringDeductions as $item) {
                            $componentName = $item['componentName'] ?? 'Unknown Component';
                            $headerKey = 'deduction_' . str_replace(' ', '_', strtolower($componentName));
                            $value = (float)($item['calculatedValue'] ?? 0);
                            $row[$headerKey] = $value;
                        }
                        
                        $excelData[] = $row;
                        continue;
                    }
                    
                    // Parse current export month
                    $currentMonth = \Carbon\Carbon::parse($request->year . '-' . $request->month . '-01');
                    
                    // Check if effective date is BEFORE the current month (arrears only for past months)
                    if ($effectiveFrom->lt($currentMonth)) {
                        // Calculate months between effective date and current month
                        $monthsDiff = $effectiveFrom->diffInMonths($currentMonth);
                        
                        $arrearStatus = 'Arrears Due';
                        $arrearsEffectiveFrom = $effectiveFrom->format('M Y');
                        $arrearsMonthCount = $monthsDiff;
                        
                        // Calculate arrears for each gross component
                        foreach ($grossList as $item) {
                            $componentName = $item['componentName'] ?? 'Unknown Component';
                            $monthlyValue = (float)($item['calculatedValue'] ?? 0);
                            $arrearsValue = $monthlyValue * $monthsDiff;
                            $totalGrossArrears += $arrearsValue;
                            $arrearsGrossBreakup[$componentName] = $arrearsValue;
                        }
                        
                        // Calculate arrears for each deduction component
                        foreach ($recurringDeductions as $item) {
                            $componentName = $item['componentName'] ?? 'Unknown Component';
                            $monthlyValue = (float)($item['calculatedValue'] ?? 0);
                            $arrearsValue = $monthlyValue * $monthsDiff;
                            $totalDeductionArrears += $arrearsValue;
                            $arrearsDeductionBreakup[$componentName] = $arrearsValue;
                        }
                        
                        $netArrearsPayable = $totalGrossArrears - $totalDeductionArrears;
                        $arrearsStats['employeesWithArrears']++;
                        $arrearsStats['employeesWithArrearsDetails'][] = [
                            'empCode' => $record->empCode,
                            'empName' => $fullName,
                            'effectiveFrom' => $arrearsEffectiveFrom,
                            'monthsCount' => $arrearsMonthCount,
                            'arrearsAmount' => round($netArrearsPayable, 2)
                        ];
                    } else {
                        // Effective date is in current month or future - no arrears yet
                        $arrearStatus = 'No Arrears';
                        $arrearsStats['employeesWithoutArrears']++;
                    }
                } else {
                    $arrearsStats['employeesWithoutRevision']++;
                    $arrearsStats['employeesWithoutRevisionDetails'][] = [
                        'empCode' => $record->empCode,
                        'empName' => $fullName
                    ];
                }

                $netTakeHomeWithArrears = $netTakeHomeMonthly + $netArrearsPayable;

                // Fetch paid days from attendance summary
                $attendanceSummary = $attendanceSummaries->get($record->empCode);
                $paidDaysCurrent = $attendanceSummary ? (float)$attendanceSummary->paidDays : 0;
                $paidDaysWithArrears = $paidDaysCurrent; // Same as current for arrears calculation

                // Build row data
                $row = [
                    'serialNo' => $serialNo++,
                    'empCode' => $record->empCode ?? '',
                    'empName' => $fullName ?: 'N/A',
                    'designation' => $employmentDetail->Designation ?? 'N/A',
                    'paidDaysCurrent' => round($paidDaysCurrent, 2),
                    'paidDaysWithArrears' => round($paidDaysWithArrears, 2),
                    'arrearStatus' => $arrearStatus,
                    'arrearsEffectiveFrom' => $arrearsEffectiveFrom,
                    'arrearsMonthCount' => $arrearsMonthCount,
                    'monthlyTotalGross' => round($monthlyTotalGross, 2),
                    'monthlyTotalDeductions' => round($monthlyTotalDeductions, 2),
                    'totalGrossArrears' => round($totalGrossArrears, 2),
                    'totalDeductionArrears' => round($totalDeductionArrears, 2),
                    'netArrearsPayable' => round($netArrearsPayable, 2),
                    'netTakeHomeWithArrears' => round($netTakeHomeWithArrears, 2),
                    'status' => $record->status,
                ];

                // Initialize ALL dynamic headers with 0 first
                foreach ($dynamicHeaders['gross'] as $headerKey => $headerName) {
                    $row[$headerKey] = 0;
                    $row['arrears_' . $headerKey] = 0;
                    if (!isset($totals[$headerKey])) {
                        $totals[$headerKey] = 0;
                        $totals['arrears_' . $headerKey] = 0;
                    }
                }
                foreach ($dynamicHeaders['deductions'] as $headerKey => $headerName) {
                    $row[$headerKey] = 0;
                    $row['arrears_' . $headerKey] = 0;
                    if (!isset($totals[$headerKey])) {
                        $totals[$headerKey] = 0;
                        $totals['arrears_' . $headerKey] = 0;
                    }
                }

                // Populate actual values for gross components
                foreach ($grossList as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'gross_' . str_replace(' ', '_', strtolower($componentName));
                    $value = (float)($item['calculatedValue'] ?? 0);
                    $row[$headerKey] = $value;
                    $totals[$headerKey] = ($totals[$headerKey] ?? 0) + $value;
                    
                    // Arrears value
                    $arrearsValue = $arrearsGrossBreakup[$componentName] ?? 0;
                    $row['arrears_' . $headerKey] = $arrearsValue;
                    $totals['arrears_' . $headerKey] = ($totals['arrears_' . $headerKey] ?? 0) + $arrearsValue;
                }
                
                // Populate actual values for deduction components
                foreach ($recurringDeductions as $item) {
                    $componentName = $item['componentName'] ?? 'Unknown Component';
                    $headerKey = 'deduction_' . str_replace(' ', '_', strtolower($componentName));
                    $value = (float)($item['calculatedValue'] ?? 0);
                    $row[$headerKey] = $value;
                    $totals[$headerKey] = ($totals[$headerKey] ?? 0) + $value;
                    
                    // Arrears value
                    $arrearsValue = $arrearsDeductionBreakup[$componentName] ?? 0;
                    $row['arrears_' . $headerKey] = $arrearsValue;
                    $totals['arrears_' . $headerKey] = ($totals['arrears_' . $headerKey] ?? 0) + $arrearsValue;
                }

                // Add to summary totals
                $totals['monthlyTotalGross'] = ($totals['monthlyTotalGross'] ?? 0) + $monthlyTotalGross;
                $totals['monthlyTotalDeductions'] = ($totals['monthlyTotalDeductions'] ?? 0) + $monthlyTotalDeductions;
                $totals['totalGrossArrears'] = ($totals['totalGrossArrears'] ?? 0) + $totalGrossArrears;
                $totals['totalDeductionArrears'] = ($totals['totalDeductionArrears'] ?? 0) + $totalDeductionArrears;
                $totals['netArrearsPayable'] = ($totals['netArrearsPayable'] ?? 0) + $netArrearsPayable;
                $totals['netTakeHomeWithArrears'] = ($totals['netTakeHomeWithArrears'] ?? 0) + $netTakeHomeWithArrears;

                $excelData[] = $row;
            }

            // Create totals row
            $totalsRow = [
                'serialNo' => '',
                'empCode' => 'TOTAL',
                'empName' => '',
                'designation' => '',
                'paidDaysCurrent' => '',
                'paidDaysWithArrears' => '',
                'arrearStatus' => '',
                'arrearsEffectiveFrom' => '',
                'arrearsMonthCount' => '',
                'monthlyTotalGross' => round($totals['monthlyTotalGross'] ?? 0, 2),
                'monthlyTotalDeductions' => round($totals['monthlyTotalDeductions'] ?? 0, 2),
                'totalGrossArrears' => round($totals['totalGrossArrears'] ?? 0, 2),
                'totalDeductionArrears' => round($totals['totalDeductionArrears'] ?? 0, 2),
                'netArrearsPayable' => round($totals['netArrearsPayable'] ?? 0, 2),
                'netTakeHomeWithArrears' => round($totals['netTakeHomeWithArrears'] ?? 0, 2),
                'status' => '',
            ];

            // Add dynamic totals
            foreach ($dynamicHeaders as $key => $value) {
                $totalsRow[$key] = round($totals[$key] ?? 0, 2);
                $totalsRow['arrears_' . $key] = round($totals['arrears_' . $key] ?? 0, 2);
            }

            // Add totals row to data
            $excelData[] = $totalsRow;

            // Company and arrears information
            $companyInfo = [
                'companyName' => $request->companyName,
                'year' => $request->year,
                'month' => $request->month,
                'subBranch' => $request->subBranch ?? 'All SubBranches'
            ];

            $arrearsInfo = [
                'totalEmployees' => $arrearsStats['totalEmployees'],
                'totalEmployeesWithArrears' => $arrearsStats['employeesWithArrears'],
                'employeesWithoutRevision' => $arrearsStats['employeesWithoutRevision'],
                'employeesWithArrearsDetails' => $arrearsStats['employeesWithArrearsDetails'],
                'employeesWithoutRevisionDetails' => $arrearsStats['employeesWithoutRevisionDetails']
            ];

            // Generate filename
            $subBranchSuffix = $request->has('subBranch') && !empty($request->subBranch) ? "_{$request->subBranch}" : '';
            $fileName = "SalarySheet_WithArrears_{$request->companyName}_{$request->month}_{$request->year}{$subBranchSuffix}.xlsx";

            // Note: We still generate Excel even if no arrears found
            // The Excel will show "No Arrears" status for employees without revision
            // Only return error if NO payroll records exist at all (handled earlier in the code)

            // Store Excel file temporarily, then return as download
            $tempPath = 'temp_arrears_export_' . time() . '.xlsx';
            Excel::store(new PayrollArrearsExport($excelData, $dynamicHeaders, $companyInfo, $arrearsInfo), $tempPath, 'local');

            if (Storage::exists($tempPath)) {
                $fullPath = storage_path('app/' . $tempPath);

                // Calculate arrears months range if available
                $arrearsMonthsRange = '';
                if ($arrearsStats['employeesWithArrears'] > 0 && !empty($arrearsStats['employeesWithArrearsDetails'])) {
                    $firstEmployee = $arrearsStats['employeesWithArrearsDetails'][0];
                    if (isset($firstEmployee['effectiveFrom']) && isset($firstEmployee['monthsCount'])) {
                        $arrearsMonthsRange = $firstEmployee['monthsCount'] . ' months (from ' . $firstEmployee['effectiveFrom'] . ')';
                    }
                }

                $headers = [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'X-Total-Employees' => $arrearsStats['totalEmployees'],
                    'X-Employees-With-Arrears' => $arrearsStats['employeesWithArrears'],
                    'X-Arrears-Months' => $arrearsMonthsRange,
                    'Access-Control-Expose-Headers' => 'X-Total-Employees, X-Employees-With-Arrears, X-Arrears-Months'
                ];

                // Return a BinaryFileResponse and delete the temp file after sending
                return response()->download($fullPath, $fileName, $headers)->deleteFileAfterSend(true);
            }

            // Fallback to original method
            return Excel::download(new PayrollArrearsExport($excelData, $dynamicHeaders, $companyInfo, $arrearsInfo), $fileName);

        } catch (\Exception $e) {
            abort(500, 'Error in exporting payroll data with arrears: ' . $e->getMessage());
        }
    }

    /**
     * Calculate salary totals from payroll components
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
     * Rollback/Delete initiated salary process for selected employees
     * Deletes payroll records with status 'Initiated'
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rollbackInitiatedSalary(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'empCodes' => 'required|array|min:1',
            'empCodes.*' => 'required|string|max:20',
        ]);

        try {
            // Normalize month to month name
            $month = $request->month;
            if (is_numeric($month) && $month >= 1 && $month <= 12) {
                $month = date('F', mktime(0, 0, 0, (int)$month, 1));
            }

            // Find initiated salary records for the specified employees
            $initiatedRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month)
                ->where('status', 'Initiated')
                ->whereIn('empCode', $request->empCodes)
                ->get();

            if ($initiatedRecords->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No initiated salary records found for the specified employees',
                    'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}",
                    'requested_employees' => $request->empCodes,
                    'found_employees' => []
                ], 404);
            }

            $deletedEmpCodes = $initiatedRecords->pluck('empCode')->toArray();
            $notFoundEmpCodes = array_diff($request->empCodes, $deletedEmpCodes);
            
            // Delete the records
            $deletedCount = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month)
                ->where('status', 'Initiated')
                ->whereIn('empCode', $request->empCodes)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => "Successfully rolled back initiated salary for {$deletedCount} employees",
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}",
                'summary' => [
                    'requested_employees' => count($request->empCodes),
                    'successfully_deleted' => $deletedCount,
                    'not_found' => count($notFoundEmpCodes)
                ],
                'deleted_employees' => $deletedEmpCodes,
                'not_found_employees' => $notFoundEmpCodes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error rolling back initiated salary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback/Delete released salary process for selected employees
     * Deletes payroll records with status 'Released'
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rollbackReleasedSalary(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'empCodes' => 'required|array|min:1',
            'empCodes.*' => 'required|string|max:20',
        ]);

        try {
            // Normalize month to month name
            $month = $request->month;
            if (is_numeric($month) && $month >= 1 && $month <= 12) {
                $month = date('F', mktime(0, 0, 0, (int)$month, 1));
            }

            // Find released salary records for the specified employees
            $releasedRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month)
                ->where('status', 'Released')
                ->whereIn('empCode', $request->empCodes)
                ->get();

            if ($releasedRecords->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No released salary records found for the specified employees',
                    'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}",
                    'requested_employees' => $request->empCodes,
                    'found_employees' => []
                ], 404);
            }

            $deletedEmpCodes = $releasedRecords->pluck('empCode')->toArray();
            $notFoundEmpCodes = array_diff($request->empCodes, $deletedEmpCodes);
            
            // Delete the records
            $deletedCount = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month)
                ->where('status', 'Released')
                ->whereIn('empCode', $request->empCodes)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => "Successfully rolled back released salary for {$deletedCount} employees",
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}",
                'summary' => [
                    'requested_employees' => count($request->empCodes),
                    'successfully_deleted' => $deletedCount,
                    'not_found' => count($notFoundEmpCodes)
                ],
                'deleted_employees' => $deletedEmpCodes,
                'not_found_employees' => $notFoundEmpCodes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error rolling back released salary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete entire salary process for a specific month (all employees, all statuses)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSalaryProcessByMonth(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
        ]);

        try {
            // Normalize month to month name
            $month = $request->month;
            if (is_numeric($month) && $month >= 1 && $month <= 12) {
                $month = date('F', mktime(0, 0, 0, (int)$month, 1));
            }

            // Get all records for this month before deletion
            $monthRecords = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month)
                ->get();

            if ($monthRecords->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No salary records found for the specified month',
                    'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}"
                ], 404);
            }

            // Get status breakdown before deletion
            $statusBreakdown = $monthRecords->groupBy('status')->map(function ($records) {
                return [
                    'count' => $records->count(),
                    'employees' => $records->pluck('empCode')->toArray()
                ];
            });

            $totalRecords = $monthRecords->count();
            
            // Delete all records for this month
            $deletedCount = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $month)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => "Successfully deleted entire salary process for {$month} {$request->year}",
                'filter' => "corpId: {$request->corpId}, companyName: {$request->companyName}, year: {$request->year}, month: {$month}",
                'summary' => [
                    'total_records_deleted' => $deletedCount,
                    'status_breakdown' => $statusBreakdown
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting salary process: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate and download a salary slip PDF for a single employee with dynamic company name.
     * Unlike the custom PDF endpoint, this does NOT have any hardcoded company defaults —
     * the company name is required and all company details come from the database.
     *
     * @param string $corpId
     * @param string $empCode
     * @param string $year
     * @param string $month
     * @param string $companyName  (required)
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadDynamicSalarySlipPdf($corpId, $empCode, $year, $month, $companyName)
    {
        try {
            // Normalize month: accept numeric (1-12) or month name ("January", etc.)
            if (is_numeric($month)) {
                $monthNumber = (int)$month;
                $monthName = date('F', mktime(0, 0, 0, $monthNumber, 1));
            } else {
                $monthName = ucfirst(strtolower($month));
                $monthNumber = (int)date('n', strtotime($monthName));
            }

            // Query payroll with mandatory companyName filter
            $payroll = EmployeePayrollSalaryProcess::where('corpId', $corpId)
                ->where('empCode', $empCode)
                ->where('year', $year)
                ->where('month', $monthName)
                ->where('companyName', $companyName)
                ->first();

            if (!$payroll) {
                abort(404, 'Payroll record not found for the specified employee, period, and company.');
            }

            // Fetch related data
            $employeeDetail = \App\Models\EmployeeDetail::where('corp_id', $corpId)->where('EmpCode', $empCode)->first();
            $employmentDetail = \App\Models\EmploymentDetail::where('corp_id', $corpId)->where('EmpCode', $empCode)->first();
            $companyDetails = \App\Models\CompanyDetails::where('corp_id', $corpId)->first();
            $statutoryDetail = \App\Models\EmployeeStatutoryDetail::where('corp_id', $corpId)->where('EmpCode', $empCode)->first();
            $bankDetail = \App\Models\EmployeeBankDetail::where('corp_id', $corpId)->where('empcode', $empCode)->first();

            $attendanceSummary = $this->getAttendanceSummaryForPayslip(
                $corpId,
                $empCode,
                $year,
                $monthName,
                $monthNumber
            );

            $salaryStructure = \App\Models\EmployeeSalaryStructure::where('corpId', $corpId)
                ->where('companyName', $payroll->companyName)
                ->where('empCode', $empCode)
                ->where('year', $year)
                ->first();

            // Process payroll data
            $grossList = json_decode($payroll->grossList, true) ?: [];
            $otherAllowances = json_decode($payroll->otherAllowances, true) ?: [];
            $otherBenefits = json_decode($payroll->otherBenefits, true) ?: [];
            $earningsBreakup = $this->buildPayslipEarningsBreakup($grossList, $otherAllowances, $otherBenefits);
            $earnings = $earningsBreakup['rows'];
            $deductions = json_decode($payroll->recurringDeduction, true) ?: [];
            $totalEarnings = (float)($earningsBreakup['totalCombined'] ?? 0);
            $totalDeductions = array_sum(array_column($deductions, 'calculatedValue'));
            $netPay = $totalEarnings - $totalDeductions;
            $attendanceArrearDays = $this->resolveAttendanceArrearDays($attendanceSummary);
            $incrementArrearDays = $this->calculateIncrementArrearDaysForStructure($salaryStructure, $monthName, $year);

            // Prepare data for the dynamic view (no hardcoded company defaults)
            $data = [
                'company' => $companyDetails,
                'dynamicCompanyName' => $companyName,
                'employee' => $employeeDetail,
                'employment' => $employmentDetail,
                'statutory' => $statutoryDetail,
                'bank' => $bankDetail,
                'payroll' => $payroll,
                'monthName' => $monthName,
                'year' => $year,
                'attendance' => $attendanceSummary,
                'earnings' => $earnings,
                'deductions' => $deductions,
                'totalMonthlyEarnings' => (float)($earningsBreakup['totalMonthly'] ?? 0),
                'totalArrearEarnings' => (float)($earningsBreakup['totalArrear'] ?? 0),
                'totalEarnings' => $totalEarnings,
                'totalDeductions' => $totalDeductions,
                'netPay' => $netPay,
                'netPayInWords' => $this->numberToWords($netPay),
                'payDays' => $attendanceSummary ? $attendanceSummary->paidDays : 30,
                'attendanceArrearDays' => $attendanceArrearDays,
                'incrementArrearDays' => $incrementArrearDays,
            ];

            // Generate PDF using the dynamic template (no MACO defaults)
            $html = view('salary-slip-dynamic-pdf', $data)->render();

            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = "Payslip_{$empCode}_{$monthName}_{$year}.pdf";

            return response()->streamDownload(function () use ($dompdf) {
                echo $dompdf->output();
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating dynamic single salary slip: ' . $e->getMessage());
            abort(500, 'Error generating salary slip: ' . $e->getMessage());
        }
    }

    /**
     * Generate and download salary slip PDFs for all employees of a given company in a ZIP file.
     * The company name is dynamic (required in request body) — no hardcoded company defaults.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadAllDynamicSalarySlipsPdf(Request $request)
    {
        $request->validate([
            'corpId' => 'required|string|max:10',
            'companyName' => 'required|string|max:100',
            'year' => 'required|string|max:4',
            'month' => 'required|string|max:50',
            'status' => 'nullable|string',
        ]);

        try {
            // Normalize month
            if (is_numeric($request->month)) {
                $monthNumber = (int)$request->month;
                $monthName = date('F', mktime(0, 0, 0, $monthNumber, 1));
            } else {
                $monthName = ucfirst(strtolower($request->month));
                $monthNumber = (int)date('n', strtotime($monthName));
            }

            // Fetch payroll records filtered by dynamic companyName
            $query = EmployeePayrollSalaryProcess::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->where('month', $monthName);

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            $payrollRecords = $query->get();

            if ($payrollRecords->isEmpty()) {
                abort(404, 'No payroll records found for the specified criteria.');
            }

            // Bulk fetch all required data
            $empCodes = $payrollRecords->pluck('empCode')->unique()->toArray();

            $employeeDetails = \App\Models\EmployeeDetail::where('corp_id', $request->corpId)->whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            $employmentDetails = \App\Models\EmploymentDetail::where('corp_id', $request->corpId)->whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            $companyDetails = \App\Models\CompanyDetails::where('corp_id', $request->corpId)->first();
            $statutoryDetails = \App\Models\EmployeeStatutoryDetail::where('corp_id', $request->corpId)->whereIn('EmpCode', $empCodes)->get()->keyBy('EmpCode');
            $bankDetails = \App\Models\EmployeeBankDetail::where('corp_id', $request->corpId)->whereIn('empcode', $empCodes)->get()->keyBy('empcode');

            $salaryStructures = \App\Models\EmployeeSalaryStructure::where('corpId', $request->corpId)
                ->where('companyName', $request->companyName)
                ->where('year', $request->year)
                ->whereIn('empCode', $empCodes)
                ->get()->keyBy('empCode');

            $attendanceSummaries = \App\Models\EmployeeAttendanceSummary::where('corpId', $request->corpId)
                ->where('year', $request->year)
                ->whereIn('month', [$monthName, (string)$monthNumber])
                ->whereIn('empCode', $empCodes)
                ->get()
                ->groupBy('empCode');

            // Prepare for PDF generation
            $tempDir = storage_path('app/temp/dynamic_salary_slips_' . time());
            File::makeDirectory($tempDir, 0755, true);
            $generatedFiles = [];

            // Loop and generate PDFs
            foreach ($payrollRecords as $payroll) {
                $empCode = $payroll->empCode;

                $grossList = json_decode($payroll->grossList, true) ?: [];
                $otherAllowances = json_decode($payroll->otherAllowances, true) ?: [];
                $otherBenefits = json_decode($payroll->otherBenefits, true) ?: [];
                $earningsBreakup = $this->buildPayslipEarningsBreakup($grossList, $otherAllowances, $otherBenefits);
                $earnings = $earningsBreakup['rows'];
                $deductions = json_decode($payroll->recurringDeduction, true) ?: [];
                $totalEarnings = (float)($earningsBreakup['totalCombined'] ?? 0);
                $totalDeductions = array_sum(array_column($deductions, 'calculatedValue'));
                $netPay = $totalEarnings - $totalDeductions;
                $attendanceGroup = $attendanceSummaries->get($empCode, collect());
                $attendance = $attendanceGroup->firstWhere('month', $monthName) ?? $attendanceGroup->first();
                $salaryStructure = $salaryStructures->get($empCode);
                $attendanceArrearDays = $this->resolveAttendanceArrearDays($attendance);
                $incrementArrearDays = $this->calculateIncrementArrearDaysForStructure($salaryStructure, $monthName, $request->year);

                $data = [
                    'company' => $companyDetails,
                    'dynamicCompanyName' => $request->companyName,
                    'employee' => $employeeDetails->get($empCode),
                    'employment' => $employmentDetails->get($empCode),
                    'statutory' => $statutoryDetails->get($empCode),
                    'bank' => $bankDetails->get($empCode),
                    'payroll' => $payroll,
                    'monthName' => $monthName,
                    'year' => $request->year,
                    'attendance' => $attendance,
                    'earnings' => $earnings,
                    'deductions' => $deductions,
                    'totalMonthlyEarnings' => (float)($earningsBreakup['totalMonthly'] ?? 0),
                    'totalArrearEarnings' => (float)($earningsBreakup['totalArrear'] ?? 0),
                    'totalEarnings' => $totalEarnings,
                    'totalDeductions' => $totalDeductions,
                    'netPay' => $netPay,
                    'netPayInWords' => $this->numberToWords($netPay),
                    'payDays' => $attendance->paidDays ?? 30,
                    'attendanceArrearDays' => $attendanceArrearDays,
                    'incrementArrearDays' => $incrementArrearDays,
                ];

                // Use the dynamic template (no hardcoded company defaults)
                $html = view('salary-slip-dynamic-pdf', $data)->render();

                $options = new \Dompdf\Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true);

                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                $filename = "Payslip_{$empCode}_{$monthName}_{$request->year}.pdf";
                $filePath = $tempDir . '/' . $filename;
                file_put_contents($filePath, $dompdf->output());
                $generatedFiles[] = $filePath;
            }

            // Create ZIP file
            if (empty($generatedFiles)) {
                File::deleteDirectory($tempDir);
                abort(500, 'Failed to generate any PDF files.');
            }

            $safeCompanyName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $request->companyName);
            $zipFilename = "Payslips_{$safeCompanyName}_{$monthName}_{$request->year}.zip";
            $zipPath = $tempDir . '/' . $zipFilename;

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                foreach ($generatedFiles as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
            } else {
                File::deleteDirectory($tempDir);
                abort(500, 'Failed to create ZIP file.');
            }

            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            if (isset($tempDir) && File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            Log::error('Error generating bulk dynamic salary slips: ' . $e->getMessage());
            abort(500, 'Error generating bulk dynamic salary slips: ' . $e->getMessage());
        }
    }

    /**
     * Download all dynamic salary slips as PDF (GET method for FlutterFlow compatibility)
     */
    public function downloadAllDynamicSalarySlipsPdfGet(Request $request)
    {
        return $this->downloadAllDynamicSalarySlipsPdf($request);
    }
}

