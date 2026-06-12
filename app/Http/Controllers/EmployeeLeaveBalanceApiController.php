<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveSetting;
use App\Models\LeaveTypeBasicConfiguration;
use App\Models\LeaveTypeFullConfiguration;
use App\Models\EmployeeDetail;
use App\Models\EmploymentDetail;
use App\Models\UserLogin;
use Carbon\Carbon;

class EmployeeLeaveBalanceApiController extends Controller
{
    /**
     * Check if the user is admin
     */
    private function isAdmin($corpId, $empCode)
    {
        $user = UserLogin::where('corp_id', $corpId)
            ->where('empcode', $empCode)
            ->where('admin_yn', 1)
            ->first();
        
        return $user !== null;
    }

    /**
     * Get employee full name
     */
    private function getEmployeeFullName($corpId, $empCode)
    {
        $employee = EmployeeDetail::where('corp_id', $corpId)
            ->where('EmpCode', $empCode)
            ->first();
        
        if ($employee) {
            $fullName = trim($employee->FirstName . ' ' . ($employee->MiddleName ?? '') . ' ' . $employee->LastName);
            return preg_replace('/\s+/', ' ', $fullName);
        }
        
        return null;
    }

    /**
     * Normalize leave type/leave name strings for consistent matching.
     */
    private function normalizeLeaveType($value)
    {
        return strtolower(trim((string) $value));
    }

    /**
     * Build leave configs from company/year leave_settings with fallback to basic config.
     */
    private function getLeaveConfigurations($corpId, $companyName, $year)
    {
        $basicConfigs = LeaveTypeBasicConfiguration::where('corpid', $corpId)
            ->where('isConfigurationCompletedYN', 1)
            ->get();

        $basicByType = [];
        foreach ($basicConfigs as $basic) {
            $basicByType[$this->normalizeLeaveType($basic->leaveName)] = $basic;
        }

        $settings = LeaveSetting::where('corp_id', $corpId)
            ->where('company_name', $companyName)
            ->where('year', (int) $year)
            ->get();

        $configurations = [];

        if ($settings->isNotEmpty()) {
            foreach ($settings as $setting) {
                $normalizedType = $this->normalizeLeaveType($setting->leave_type);
                $basic = $basicByType[$normalizedType] ?? null;

                $fullConfig = null;
                if ($basic) {
                    $fullConfig = LeaveTypeFullConfiguration::where('puid', $basic->puid)
                        ->where('corpid', $corpId)
                        ->first();
                }

                $monthlyAllocation = (float) ($setting->monthly_allocation ?? 0);
                $yearlyAllocation = (float) ($setting->yearly_allocation ?? 0);
                $creditType = $monthlyAllocation > 0 ? 'monthly' : 'yearly';

                $configurations[] = [
                    'puid' => $basic ? $basic->puid : ('setting-' . $normalizedType),
                    'leave_code' => $basic && !empty($basic->leaveCode)
                        ? $basic->leaveCode
                        : strtoupper(substr($normalizedType, 0, 1)) . '-SET',
                    'leave_name' => $basic && !empty($basic->leaveName)
                        ? $basic->leaveName
                        : $setting->leave_type,
                    'credit_type' => $creditType,
                    'yearly_allocation' => $yearlyAllocation,
                    'monthly_allocation' => $monthlyAllocation,
                    'full' => $fullConfig,
                ];
            }

            return $configurations;
        }

        // Fallback for corp/company where leave_settings are not configured yet.
        foreach ($basicConfigs as $basic) {
            $fullConfig = LeaveTypeFullConfiguration::where('puid', $basic->puid)
                ->where('corpid', $corpId)
                ->first();

            $limitDays = (float) ($basic->LimitDays ?? 0);
            $creditType = strtolower($basic->leaveTypeTobeCredited ?? 'yearly');

            $configurations[] = [
                'puid' => $basic->puid,
                'leave_code' => $basic->leaveCode,
                'leave_name' => $basic->leaveName,
                'credit_type' => $creditType === 'monthly' ? 'monthly' : 'yearly',
                'yearly_allocation' => $limitDays,
                'monthly_allocation' => $creditType === 'monthly'
                    ? $this->calculateMonthlyCredit($limitDays, 'monthly')
                    : 0,
                'full' => $fullConfig,
            ];
        }

        return $configurations;
    }

    /**
     * Calculate monthly leave credit based on configuration
     */
    private function calculateMonthlyCredit($limitDays, $creditType)
    {
        if (strtolower($creditType) === 'monthly') {
            return round($limitDays / 12, 2);
        }
        return $limitDays;
    }

    /**
     * A) Allot leaves to all employees (Admin only)
     * - No duplicate leaves for the year
     * - Only new employees get leaves if re-allotting
     * - Carry forward logic for next year
     * - Monthly leave auto-credit
     */
    public function allotLeaves(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'emp_code' => 'required|string', // Admin's emp_code
            'year' => 'required|integer|min:2020|max:2100',
            'company_name' => 'required|string'
        ]);

        $corpId = $request->corp_id;
        $adminEmpCode = $request->emp_code;
        $year = $request->year;
        $companyName = $request->company_name;
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->month;

        // Check if user is admin
        if (!$this->isAdmin($corpId, $adminEmpCode)) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied. Only admin users can allot leaves.'
            ], 403);
        }

        // Get employees filtered by corp_id + company_name via EmploymentDetail
        $empCodes = EmploymentDetail::where('corp_id', $corpId)
            ->where('company_name', $companyName)
            ->pluck('EmpCode')
            ->toArray();

        if (empty($empCodes)) {
            return response()->json([
                'status' => false,
                'message' => 'No employees found for this corp_id and company_name.'
            ], 404);
        }

        $employees = EmployeeDetail::where('corp_id', $corpId)
            ->whereIn('EmpCode', $empCodes)
            ->get();

        if ($employees->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No employees found for this corp_id and company_name.'
            ], 404);
        }

        // Get leave configurations
        $leaveConfigs = $this->getLeaveConfigurations($corpId, $companyName, $year);
        
        if (empty($leaveConfigs)) {
            return response()->json([
                'status' => false,
                'message' => 'No leave type configurations found for this corp_id. Please configure leave types first.'
            ], 404);
        }

        $allottedCount = 0;
        $skippedCount = 0;
        $carryForwardCount = 0;
        $allottedEmployees = [];
        $skippedEmployees = [];

        DB::beginTransaction();
        
        try {
            foreach ($employees as $employee) {
                $empCode = $employee->EmpCode;
                $empFullName = $this->getEmployeeFullName($corpId, $empCode);
                
                if (!$empFullName) {
                    $empFullName = trim($employee->FirstName . ' ' . ($employee->MiddleName ?? '') . ' ' . $employee->LastName);
                    $empFullName = preg_replace('/\s+/', ' ', $empFullName);
                }

                foreach ($leaveConfigs as $config) {
                    $leaveTypePuid = $config['puid'];
                    $leaveCode = $config['leave_code'];
                    $leaveName = $config['leave_name'];
                    $full = $config['full'];

                    // Calculate carry forward from previous year
                    $carryForward = 0;
                    $shouldLapsePreviousYear = false;
                    $previousYearBalance = EmployeeLeaveBalance::where('corp_id', $corpId)
                        ->where('emp_code', $empCode)
                        ->where('leave_type_puid', $leaveTypePuid)
                        ->where('year', $year - 1)
                        ->where('company_name', $companyName)
                        ->first();

                    if ($previousYearBalance && $full) {
                        // Check if carry forward is allowed
                        $lapseLeave = strtolower($full->lapseLeaveYn ?? 'yes');
                        
                        if ($lapseLeave === 'no') {
                            // Carry forward is allowed
                            $maxCarryForwardType = strtolower($full->maxCarryForwardLeavesType ?? 'zero');
                            $maxCarryForwardBalance = (float)($full->maxCarryForwardLeavesBalance ?? 0);
                            
                            if ($maxCarryForwardType === 'all') {
                                $carryForward = $previousYearBalance->balance;
                            } elseif ($maxCarryForwardType === 'days' && $maxCarryForwardBalance > 0) {
                                $carryForward = min($previousYearBalance->balance, $maxCarryForwardBalance);
                            }
                            // If 'zero', carryForward remains 0
                            
                            if ($carryForward > 0) {
                                $carryForwardCount++;
                            }
                        }
                        
                        // Mark previous year's leave as lapsed if applicable (after create succeeds)
                        $shouldLapsePreviousYear = $lapseLeave === 'yes';
                    }

                    // Calculate total allotted based on credit type
                    $creditType = strtolower((string) ($config['credit_type'] ?? 'yearly'));
                    $yearlyAllocation = (float) ($config['yearly_allocation'] ?? 0);
                    $monthlyAllocation = (float) ($config['monthly_allocation'] ?? 0);
                    $totalAllotted = $yearlyAllocation;
                    
                    // For monthly credited leaves in current year, calculate based on remaining months
                    if ($creditType === 'monthly' && $monthlyAllocation > 0 && $year == $currentDate->year) {
                        // Monthly credit mode: only credit months elapsed in current year.
                        $totalAllotted = $monthlyAllocation * $currentMonth;
                    }

                    $totalWithCarryForward = $totalAllotted + $carryForward;

                    // Check if record already exists to avoid race-condition duplicate key errors
                    $existing = EmployeeLeaveBalance::where('corp_id', $corpId)
                        ->where('emp_code', $empCode)
                        ->where('leave_type_puid', $leaveTypePuid)
                        ->where('year', $year)
                        ->where('company_name', $companyName)
                        ->exists();

                    if ($existing) {
                        $skippedCount++;
                        if (!in_array($empCode, $skippedEmployees)) {
                            $skippedEmployees[] = $empCode;
                        }
                        continue;
                    }

                    try {
                        $leaveBalance = EmployeeLeaveBalance::create([
                            'corp_id' => $corpId,
                            'emp_code' => $empCode,
                            'leave_type_puid' => $leaveTypePuid,
                            'year' => $year,
                            'company_name' => $companyName,
                            'emp_full_name' => $empFullName,
                            'leave_code' => $leaveCode,
                            'leave_name' => $leaveName,
                            'total_allotted' => $totalWithCarryForward,
                            'used' => 0,
                            'balance' => $totalWithCarryForward,
                            'carry_forward' => $carryForward,
                            'month' => ($creditType === 'monthly' && $monthlyAllocation > 0)
                                ? ($year == $currentDate->year ? $currentMonth : 12)
                                : null,
                            'credit_type' => $creditType,
                            'is_lapsed' => false,
                            'last_credited_at' => $currentDate,
                        ]);
                    } catch (\Illuminate\Database\QueryException $qe) {
                        // 23000 = integrity constraint violation (duplicate key)
                        if ($qe->getCode() === '23000') {
                            $skippedCount++;
                            if (!in_array($empCode, $skippedEmployees)) {
                                $skippedEmployees[] = $empCode;
                            }
                            continue;
                        }
                        throw $qe;
                    }

                    if ($shouldLapsePreviousYear && $previousYearBalance) {
                        $previousYearBalance->update(['is_lapsed' => true]);
                    }

                    $allottedCount++;
                    if (!in_array($empCode, $allottedEmployees)) {
                        $allottedEmployees[] = $empCode;
                    }
                }
            }

            DB::commit();

            $alreadyAllotted = ($allottedCount === 0 && $skippedCount > 0);
            $message = $alreadyAllotted
                ? 'Leaves are already allotted for all employees of ' . $companyName . ' in ' . $year . '.'
                : 'Leave allotment completed successfully.';

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'total_leave_records_created' => $allottedCount,
                    'total_records_skipped' => $skippedCount,
                    'carry_forward_applied' => $carryForwardCount,
                    'employees_allotted' => count($allottedEmployees),
                    'employees_skipped' => count($skippedEmployees),
                    'already_allotted' => $alreadyAllotted,
                    'company_name' => $companyName,
                    'year' => $year
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error allotting leaves: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process monthly leave credits (to be called via scheduler or manually)
     * This will add monthly credits for employees with monthly credit type
     */
    public function processMonthlyCredits(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'emp_code' => 'required|string', // Admin's emp_code
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'company_name' => 'required|string'
        ]);

        $corpId = $request->corp_id;
        $adminEmpCode = $request->emp_code;
        $year = $request->year;
        $month = $request->month;
        $companyName = $request->company_name;

        // Check if user is admin
        if (!$this->isAdmin($corpId, $adminEmpCode)) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied. Only admin users can process monthly credits.'
            ], 403);
        }

        $updatedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();

        try {
            // Get all leave balances with monthly credit type for the given year and company
            $leaveBalances = EmployeeLeaveBalance::where('corp_id', $corpId)
                ->where('year', $year)
                ->where('credit_type', 'monthly')
                ->where('company_name', $companyName)
                ->get();

            foreach ($leaveBalances as $balance) {
                // Check if this month's credit was already processed
                if ($balance->month >= $month) {
                    $skippedCount++;
                    continue;
                }

                // Resolve monthly credit from leave_settings first (company+year specific).
                $monthlyCredit = 0;
                $leaveSetting = LeaveSetting::where('corp_id', $corpId)
                    ->where('company_name', $companyName)
                    ->where('year', $year)
                    ->whereRaw('LOWER(leave_type) = ?', [$this->normalizeLeaveType($balance->leave_name)])
                    ->first();

                if ($leaveSetting) {
                    $monthlyCredit = (float) ($leaveSetting->monthly_allocation ?? 0);
                } else {
                    // Backward-compatible fallback: infer from basic configuration.
                    $basicConfig = LeaveTypeBasicConfiguration::where('puid', $balance->leave_type_puid)->first();
                    if ($basicConfig) {
                        $limitDays = (float)$basicConfig->LimitDays;
                        $monthlyCredit = $this->calculateMonthlyCredit($limitDays, 'monthly');
                    }
                }

                if ($monthlyCredit <= 0) {
                    $skippedCount++;
                    continue;
                }
                
                // Calculate months to credit (from last credited month to current month)
                $monthsToCredit = $month - ($balance->month ?? 0);
                $additionalCredit = $monthlyCredit * $monthsToCredit;

                $balance->update([
                    'total_allotted' => $balance->total_allotted + $additionalCredit,
                    'balance' => $balance->balance + $additionalCredit,
                    'month' => $month,
                    'last_credited_at' => Carbon::now()
                ]);

                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Monthly credits processed successfully.',
                'data' => [
                    'records_updated' => $updatedCount,
                    'records_skipped' => $skippedCount,
                    'company_name' => $companyName,
                    'year' => $year,
                    'month' => $month
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error processing monthly credits: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * B) Get list of all employees with their leave balances by corp_id and optional emp_code
     */
    public function getEmployeeLeaveList(Request $request, $corpId, $empCode = null)
    {
        $year = $request->query('year', Carbon::now()->year);
        $companyName = $request->query('company_name');

        // Build query for leave balances
        $query = EmployeeLeaveBalance::where('corp_id', $corpId)
            ->where('year', $year);
        
        // If emp_code is provided and not 'ALL', filter by emp_code
        if ($empCode && strtoupper($empCode) !== 'ALL') {
            $query->where('emp_code', $empCode);
        }

        // Optional company_name filter
        if ($companyName) {
            $query->where('company_name', $companyName);
        }
        
        $leaveBalances = $query->orderBy('emp_code')
            ->orderBy('leave_code')
            ->get();

        if ($leaveBalances->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No leave balances found for this corp_id and year.',
                'data' => []
            ]);
        }

        // Group by employee
        $employeeLeaves = [];
        
        foreach ($leaveBalances as $balance) {
            $empCode = $balance->emp_code;
            
            if (!isset($employeeLeaves[$empCode])) {
                $employeeLeaves[$empCode] = [
                    'emp_code' => $empCode,
                    'emp_full_name' => $balance->emp_full_name,
                    'company_name' => $balance->company_name,
                    'year' => $year,
                    'leave_types' => []
                ];
            }
            
            $employeeLeaves[$empCode]['leave_types'][] = [
                'leave_code' => $balance->leave_code,
                'leave_name' => $balance->leave_name,
                'total_allotted' => (float)$balance->total_allotted,
                'used' => (float)$balance->used,
                'balance' => (float)$balance->balance,
                'carry_forward' => (float)$balance->carry_forward,
                'credit_type' => $balance->credit_type,
                'is_lapsed' => $balance->is_lapsed,
                'created_at' => $balance->created_at,
                'updated_at' => $balance->updated_at
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Employee leave list retrieved successfully.',
            'total_employees' => count($employeeLeaves),
            'year' => $year,
            'company_name' => $companyName,
            'data' => array_values($employeeLeaves)
        ]);
    }

    /**
     * C) Get individual employee's leave balances by corp_id and emp_code
     */
    public function getEmployeeLeaveBalance(Request $request, $corpId, $empCode)
    {
        $year = $request->query('year', Carbon::now()->year);

        // Get leave balances for the specific employee
        $leaveBalances = EmployeeLeaveBalance::where('corp_id', $corpId)
            ->where('emp_code', $empCode)
            ->where('year', $year)
            ->orderBy('leave_code')
            ->get();

        if ($leaveBalances->isEmpty()) {
            // Check if employee exists
            $employee = EmployeeDetail::where('corp_id', $corpId)
                ->where('EmpCode', $empCode)
                ->first();
            
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found with the given corp_id and emp_code.',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => false,
                'message' => 'No leaves have been allotted to this employee for the year ' . $year . '. Please contact admin to allot leaves.',
                'data' => []
            ]);
        }

        $empFullName = $leaveBalances->first()->emp_full_name;
        $leaveTypes = [];

        foreach ($leaveBalances as $balance) {
            $leaveTypes[] = [
                'leave_code' => $balance->leave_code,
                'leave_name' => $balance->leave_name,
                'total_allotted' => (float)$balance->total_allotted,
                'used' => (float)$balance->used,
                'balance' => (float)$balance->balance,
                'carry_forward' => (float)$balance->carry_forward,
                'credit_type' => $balance->credit_type,
                'is_lapsed' => $balance->is_lapsed,
                'last_credited_at' => $balance->last_credited_at,
                'created_at' => $balance->created_at,
                'updated_at' => $balance->updated_at
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Employee leave balance retrieved successfully.',
            'data' => [
                'corp_id' => $corpId,
                'emp_code' => $empCode,
                'emp_full_name' => $empFullName,
                'year' => $year,
                'total_leave_types' => count($leaveTypes),
                'leave_balances' => $leaveTypes
            ]
        ]);
    }

    /**
     * Update leave balance when leave is used (to be called from leave request approval)
     */
    public function updateLeaveUsed(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'emp_code' => 'required|string',
            'leave_type_puid' => 'required|string',
            'days_used' => 'required|numeric|min:0.5',
            'year' => 'required|integer'
        ]);

        $corpId = $request->corp_id;
        $empCode = $request->emp_code;
        $leaveTypePuid = $request->leave_type_puid;
        $daysUsed = $request->days_used;
        $year = $request->year;

        $leaveBalance = EmployeeLeaveBalance::where('corp_id', $corpId)
            ->where('emp_code', $empCode)
            ->where('leave_type_puid', $leaveTypePuid)
            ->where('year', $year)
            ->first();

        if (!$leaveBalance) {
            return response()->json([
                'status' => false,
                'message' => 'Leave balance not found for this employee and leave type.'
            ], 404);
        }

        if ($leaveBalance->balance < $daysUsed) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient leave balance. Available: ' . $leaveBalance->balance . ' days.'
            ], 400);
        }

        $leaveBalance->update([
            'used' => $leaveBalance->used + $daysUsed,
            'balance' => $leaveBalance->balance - $daysUsed
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave balance updated successfully.',
            'data' => [
                'emp_code' => $empCode,
                'leave_code' => $leaveBalance->leave_code,
                'days_deducted' => $daysUsed,
                'new_balance' => $leaveBalance->balance,
                'total_used' => $leaveBalance->used
            ]
        ]);
    }

    /**
     * Revert leave used (for leave cancellation)
     */
    public function revertLeaveUsed(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'emp_code' => 'required|string',
            'leave_type_puid' => 'required|string',
            'days_to_revert' => 'required|numeric|min:0.5',
            'year' => 'required|integer'
        ]);

        $corpId = $request->corp_id;
        $empCode = $request->emp_code;
        $leaveTypePuid = $request->leave_type_puid;
        $daysToRevert = $request->days_to_revert;
        $year = $request->year;

        $leaveBalance = EmployeeLeaveBalance::where('corp_id', $corpId)
            ->where('emp_code', $empCode)
            ->where('leave_type_puid', $leaveTypePuid)
            ->where('year', $year)
            ->first();

        if (!$leaveBalance) {
            return response()->json([
                'status' => false,
                'message' => 'Leave balance not found for this employee and leave type.'
            ], 404);
        }

        $leaveBalance->update([
            'used' => max(0, $leaveBalance->used - $daysToRevert),
            'balance' => $leaveBalance->balance + $daysToRevert
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave reverted successfully.',
            'data' => [
                'emp_code' => $empCode,
                'leave_code' => $leaveBalance->leave_code,
                'days_reverted' => $daysToRevert,
                'new_balance' => $leaveBalance->balance,
                'total_used' => $leaveBalance->used
            ]
        ]);
    }

    /**
     * Get leave summary with all details (for admin dashboard)
     */
    public function getLeaveSummary(Request $request, $corpId)
    {
        $year = $request->query('year', Carbon::now()->year);
        $companyName = $request->query('company_name');

        $query = DB::table('employee_leave_balances')
            ->where('corp_id', $corpId)
            ->where('year', $year);

        if ($companyName) {
            $query->where('company_name', $companyName);
        }

        $summary = $query->select(
                'leave_code',
                'leave_name',
                DB::raw('COUNT(DISTINCT emp_code) as total_employees'),
                DB::raw('SUM(total_allotted) as total_allotted'),
                DB::raw('SUM(used) as total_used'),
                DB::raw('SUM(balance) as total_balance'),
                DB::raw('SUM(carry_forward) as total_carry_forward')
            )
            ->groupBy('leave_code', 'leave_name')
            ->get();

        if ($summary->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No leave data found for this corp_id and year.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Leave summary retrieved successfully.',
            'year' => $year,
            'company_name' => $companyName,
            'data' => $summary
        ]);
    }

    /**
     * Get leave names for employees by corp_id and optional emp_code
     * If emp_code is not provided or is 'ALL', returns all employees' leave names
     */
    public function getLeaveNames(Request $request, $corpId, $empCode = null)
    {
        $year = $request->query('year', Carbon::now()->year);
        $companyName = $request->query('company_name');
        
        // Build query
        $query = EmployeeLeaveBalance::where('corp_id', $corpId)
            ->where('year', $year);
        
        // If emp_code is provided and not 'ALL', filter by emp_code
        if ($empCode && strtoupper($empCode) !== 'ALL') {
            $query->where('emp_code', $empCode);
        }

        // Optional company_name filter
        if ($companyName) {
            $query->where('company_name', $companyName);
        }
        
        $leaveBalances = $query->select('emp_code', 'emp_full_name', 'company_name', 'leave_code', 'leave_name', 'leave_type_puid')
            ->orderBy('emp_code')
            ->orderBy('leave_name')
            ->get();
        
        if ($leaveBalances->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No leave balances found for the given criteria.',
                'data' => []
            ]);
        }
        
        // Group by employee
        $result = [];
        foreach ($leaveBalances as $balance) {
            $empKey = $balance->emp_code;
            
            if (!isset($result[$empKey])) {
                $result[$empKey] = [
                    'emp_code' => $balance->emp_code,
                    'emp_full_name' => $balance->emp_full_name,
                    'company_name' => $balance->company_name,
                    'leave_types' => []
                ];
            }
            
            $result[$empKey]['leave_types'][] = [
                'leave_code' => $balance->leave_code,
                'leave_name' => $balance->leave_name,
                'leave_type_puid' => $balance->leave_type_puid
            ];
        }
        
        return response()->json([
            'status' => true,
            'message' => 'Leave names retrieved successfully.',
            'year' => $year,
            'company_name' => $companyName,
            'total_employees' => count($result),
            'data' => array_values($result)
        ]);
    }

    /**
     * Deduct leave from employee balance based on leave request
     * Calculates days from leave_request table using puid, from_date, to_date
     */
    public function deductLeaveByRequest(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'emp_code' => 'required|string',
            'leave_name' => 'required|string',
            'leave_request_puid' => 'required|string',
            'year' => 'nullable|integer'
        ]);

        $corpId = $request->corp_id;
        $empCode = $request->emp_code;
        $leaveName = $request->leave_name;
        $leaveRequestPuid = $request->leave_request_puid;
        $year = $request->year ?? Carbon::now()->year;

        // Get the leave request to calculate days
        $leaveRequest = DB::table('leave_request')
            ->where('puid', $leaveRequestPuid)
            ->where('corp_id', $corpId)
            ->where('empcode', $empCode)
            ->first();

        if (!$leaveRequest) {
            return response()->json([
                'status' => false,
                'message' => 'Leave request not found with the given puid, corp_id, and emp_code.'
            ], 404);
        }

        // Calculate number of days from from_date to to_date
        try {
            // Handle DD/MM/YYYY format
            $fromDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->from_date);
            $toDate = Carbon::createFromFormat('d/m/Y', $leaveRequest->to_date);
            $daysToDeduct = $fromDate->diffInDays($toDate) + 1; // +1 to include both start and end dates
        } catch (\Exception $e) {
            // Try alternative parsing if the format is different
            try {
                $fromDate = Carbon::parse($leaveRequest->from_date);
                $toDate = Carbon::parse($leaveRequest->to_date);
                $daysToDeduct = $fromDate->diffInDays($toDate) + 1;
            } catch (\Exception $e2) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid date format in leave request. From: ' . $leaveRequest->from_date . ', To: ' . $leaveRequest->to_date
                ], 400);
            }
        }

        // Find the employee leave balance by leave_name
        $leaveBalance = EmployeeLeaveBalance::where('corp_id', $corpId)
            ->where('emp_code', $empCode)
            ->where('leave_name', $leaveName)
            ->where('year', $year)
            ->first();

        if (!$leaveBalance) {
            return response()->json([
                'status' => false,
                'message' => "Leave balance not found for employee '{$empCode}' with leave type '{$leaveName}' for year {$year}."
            ], 404);
        }

        // Check if sufficient balance is available
        if ($leaveBalance->balance < $daysToDeduct) {
            return response()->json([
                'status' => false,
                'message' => "Insufficient leave balance. Required: {$daysToDeduct} days, Available: {$leaveBalance->balance} days."
            ], 400);
        }

        // Deduct the leave
        $previousBalance = $leaveBalance->balance;
        $previousUsed = $leaveBalance->used;

        $leaveBalance->update([
            'used' => $leaveBalance->used + $daysToDeduct,
            'balance' => $leaveBalance->balance - $daysToDeduct
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave deducted successfully.',
            'data' => [
                'corp_id' => $corpId,
                'emp_code' => $empCode,
                'emp_full_name' => $leaveBalance->emp_full_name,
                'leave_name' => $leaveName,
                'leave_code' => $leaveBalance->leave_code,
                'leave_request_puid' => $leaveRequestPuid,
                'from_date' => $leaveRequest->from_date,
                'to_date' => $leaveRequest->to_date,
                'days_deducted' => $daysToDeduct,
                'previous_balance' => $previousBalance,
                'new_balance' => $leaveBalance->balance,
                'previous_used' => $previousUsed,
                'new_used' => $leaveBalance->used,
                'total_allotted' => $leaveBalance->total_allotted,
                'year' => $year
            ]
        ]);
    }

    /**
     * Update an existing leave allotment for a specific company/year/leave.
     */
    public function updateAllotment(Request $request)
    {
        $validated = $request->validate([
            'corp_id' => 'required|string',
            'emp_code' => 'required|string',
            'company_name' => 'required|string',
            'leave_type_puid' => 'required|string',
            'leave_code' => 'required|string',
            'year' => 'required|integer|min:2020|max:2100',
            'total_allotted' => 'required|numeric|min:0',
            'used' => 'required|numeric|min:0',
            'balance' => 'required|numeric|min:0',
            'carry_forward' => 'nullable|numeric|min:0',
            'credit_type' => 'nullable|string|in:yearly,monthly',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        if ((float) $validated['used'] > (float) $validated['total_allotted']) {
            return response()->json([
                'status' => false,
                'message' => 'The used value cannot be greater than total allotted.',
                'errors' => [
                    'used' => ['The used value cannot be greater than total allotted.'],
                ],
            ], 422);
        }

        $expectedBalance = (float) $validated['total_allotted'] - (float) $validated['used'];
        if (abs((float) $validated['balance'] - $expectedBalance) > 0.01) {
            return response()->json([
                'status' => false,
                'message' => 'The balance must equal total_allotted minus used.',
                'errors' => [
                    'balance' => ['The balance must equal total_allotted minus used.'],
                ],
            ], 422);
        }

        DB::beginTransaction();

        try {
            $leaveBalance = EmployeeLeaveBalance::where('corp_id', $validated['corp_id'])
                ->where('emp_code', $validated['emp_code'])
                ->where('company_name', $validated['company_name'])
                ->where('leave_type_puid', $validated['leave_type_puid'])
                ->where('leave_code', $validated['leave_code'])
                ->where('year', $validated['year'])
                ->lockForUpdate()
                ->first();

            if (!$leaveBalance) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Leave allotment record not found for provided company/year/leave.',
                ], 404);
            }

            $leaveBalance->total_allotted = $validated['total_allotted'];
            $leaveBalance->used = $validated['used'];
            $leaveBalance->balance = $validated['balance'];
            $leaveBalance->carry_forward = $validated['carry_forward'] ?? 0;

            $shouldTouchCreditedAt = $request->has('credit_type') || $request->has('month');

            if ($request->filled('credit_type')) {
                $leaveBalance->credit_type = $validated['credit_type'];
            }

            if ($request->filled('month')) {
                $leaveBalance->month = $validated['month'];
            }

            if ($shouldTouchCreditedAt) {
                $leaveBalance->last_credited_at = Carbon::now();
            }

            $leaveBalance->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Leave allotment updated successfully.',
                'data' => [
                    'id' => $leaveBalance->id,
                    'corp_id' => $leaveBalance->corp_id,
                    'emp_code' => $leaveBalance->emp_code,
                    'company_name' => $leaveBalance->company_name,
                    'leave_type_puid' => $leaveBalance->leave_type_puid,
                    'leave_code' => $leaveBalance->leave_code,
                    'leave_name' => $leaveBalance->leave_name,
                    'total_allotted' => (float) $leaveBalance->total_allotted,
                    'used' => (float) $leaveBalance->used,
                    'balance' => (float) $leaveBalance->balance,
                    'carry_forward' => (float) $leaveBalance->carry_forward,
                    'credit_type' => $leaveBalance->credit_type,
                    'month' => $leaveBalance->month,
                    'year' => $leaveBalance->year,
                    'last_credited_at' => $leaveBalance->last_credited_at,
                    'updated_at' => $leaveBalance->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Error updating leave allotment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
