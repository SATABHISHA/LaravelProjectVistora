<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveTypeBasicConfiguration;
use App\Models\LeaveTypeFullConfiguration;
use App\Models\EmployeeDetail;
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
     * Get leave configuration with full details
     */
    private function getLeaveConfigurations($corpId)
    {
        $basicConfigs = LeaveTypeBasicConfiguration::where('corpid', $corpId)
            ->where('isConfigurationCompletedYN', 1)
            ->get();

        $configurations = [];
        
        foreach ($basicConfigs as $basic) {
            $fullConfig = LeaveTypeFullConfiguration::where('puid', $basic->puid)
                ->where('corpid', $corpId)
                ->first();
            
            $configurations[] = [
                'basic' => $basic,
                'full' => $fullConfig
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
            'year' => 'required|integer|min:2020|max:2100'
        ]);

        $corpId = $request->corp_id;
        $adminEmpCode = $request->emp_code;
        $year = $request->year;
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->month;

        // Check if user is admin
        if (!$this->isAdmin($corpId, $adminEmpCode)) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied. Only admin users can allot leaves.'
            ], 403);
        }

        // Get all employees for the corp_id
        $employees = EmployeeDetail::where('corp_id', $corpId)->get();
        
        if ($employees->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No employees found for this corp_id.'
            ], 404);
        }

        // Get leave configurations
        $leaveConfigs = $this->getLeaveConfigurations($corpId);
        
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
                    $basic = $config['basic'];
                    $full = $config['full'];
                    
                    // Check if leave already exists for this employee, leave type, and year
                    $existingLeave = EmployeeLeaveBalance::where('corp_id', $corpId)
                        ->where('emp_code', $empCode)
                        ->where('leave_type_puid', $basic->puid)
                        ->where('year', $year)
                        ->first();

                    if ($existingLeave) {
                        // Skip if already allotted
                        $skippedCount++;
                        if (!in_array($empCode, $skippedEmployees)) {
                            $skippedEmployees[] = $empCode;
                        }
                        continue;
                    }

                    // Calculate carry forward from previous year
                    $carryForward = 0;
                    $previousYearBalance = EmployeeLeaveBalance::where('corp_id', $corpId)
                        ->where('emp_code', $empCode)
                        ->where('leave_type_puid', $basic->puid)
                        ->where('year', $year - 1)
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
                        
                        // Mark previous year's leave as lapsed if applicable
                        if ($lapseLeave === 'yes') {
                            $previousYearBalance->update(['is_lapsed' => true]);
                        }
                    }

                    // Calculate total allotted based on credit type
                    $creditType = strtolower($basic->leaveTypeTobeCredited ?? 'yearly');
                    $limitDays = (float)$basic->LimitDays;
                    $totalAllotted = $limitDays;
                    
                    // For monthly credited leaves in current year, calculate based on remaining months
                    $monthlyCredit = null;
                    if ($creditType === 'monthly' && $year == $currentDate->year) {
                        $monthlyCredit = $this->calculateMonthlyCredit($limitDays, $creditType);
                        // For monthly, start with the months that have passed plus current month
                        $totalAllotted = $monthlyCredit * $currentMonth;
                    }

                    $totalWithCarryForward = $totalAllotted + $carryForward;

                    // Create leave balance record
                    EmployeeLeaveBalance::create([
                        'corp_id' => $corpId,
                        'emp_code' => $empCode,
                        'emp_full_name' => $empFullName,
                        'leave_type_puid' => $basic->puid,
                        'leave_code' => $basic->leaveCode,
                        'leave_name' => $basic->leaveName,
                        'total_allotted' => $totalWithCarryForward,
                        'used' => 0,
                        'balance' => $totalWithCarryForward,
                        'carry_forward' => $carryForward,
                        'year' => $year,
                        'month' => $creditType === 'monthly' ? $currentMonth : null,
                        'credit_type' => $creditType,
                        'is_lapsed' => false,
                        'last_credited_at' => $currentDate
                    ]);

                    $allottedCount++;
                    if (!in_array($empCode, $allottedEmployees)) {
                        $allottedEmployees[] = $empCode;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Leave allotment completed successfully.',
                'data' => [
                    'total_leave_records_created' => $allottedCount,
                    'total_records_skipped' => $skippedCount,
                    'carry_forward_applied' => $carryForwardCount,
                    'employees_allotted' => count($allottedEmployees),
                    'employees_skipped' => count($skippedEmployees),
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
            'month' => 'required|integer|min:1|max:12'
        ]);

        $corpId = $request->corp_id;
        $adminEmpCode = $request->emp_code;
        $year = $request->year;
        $month = $request->month;

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
            // Get all leave balances with monthly credit type for the given year
            $leaveBalances = EmployeeLeaveBalance::where('corp_id', $corpId)
                ->where('year', $year)
                ->where('credit_type', 'monthly')
                ->get();

            foreach ($leaveBalances as $balance) {
                // Check if this month's credit was already processed
                if ($balance->month >= $month) {
                    $skippedCount++;
                    continue;
                }

                // Get the leave configuration to know the monthly credit amount
                $basicConfig = LeaveTypeBasicConfiguration::where('puid', $balance->leave_type_puid)->first();
                
                if (!$basicConfig) {
                    continue;
                }

                $limitDays = (float)$basicConfig->LimitDays;
                $monthlyCredit = $this->calculateMonthlyCredit($limitDays, 'monthly');
                
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
     * B) Get list of all employees with their leave balances by corp_id
     */
    public function getEmployeeLeaveList(Request $request, $corpId)
    {
        $year = $request->query('year', Carbon::now()->year);

        // Get all leave balances for the corp_id and year
        $leaveBalances = EmployeeLeaveBalance::where('corp_id', $corpId)
            ->where('year', $year)
            ->orderBy('emp_code')
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

        $summary = DB::table('employee_leave_balances')
            ->where('corp_id', $corpId)
            ->where('year', $year)
            ->select(
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
            'data' => $summary
        ]);
    }
}
