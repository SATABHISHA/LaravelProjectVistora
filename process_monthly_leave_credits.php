<?php
/**
 * Process Monthly Leave Credits - Cron Job Script
 * 
 * This script should be run on the 1st of every month at 00:05 AM
 * 
 * Hostinger Cron Setup:
 * Command: /usr/bin/php /path/to/your/project/process_monthly_leave_credits.php
 * Schedule: 5 0 1 * * (At 00:05 on day-of-month 1)
 */

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveTypeBasicConfiguration;
use App\Models\LeaveTypeFullConfiguration;
use App\Models\EmployeeDetail;

try {
    // Set execution time limit to 0 (no time limit)
    set_time_limit(0);
    
    $currentDate = Carbon::now();
    $year = $currentDate->year;
    $month = $currentDate->month;
    
    Log::channel('daily')->info('======================================');
    Log::channel('daily')->info('Starting Monthly Leave Credits Processing');
    Log::channel('daily')->info("Year: {$year}, Month: {$month}");
    Log::channel('daily')->info('======================================');
    
    echo "======================================\n";
    echo "Processing Monthly Leave Credits\n";
    echo "Year: {$year}, Month: {$month}\n";
    echo "======================================\n\n";
    
    // Get unique corp_ids from leave configurations
    $corpIds = LeaveTypeBasicConfiguration::distinct()
        ->pluck('corpid')
        ->toArray();
    
    if (empty($corpIds)) {
        $message = "No corporate IDs found with leave configurations.";
        Log::channel('daily')->warning($message);
        echo "Warning: {$message}\n";
        exit(0);
    }
    
    $totalProcessed = 0;
    $totalSkipped = 0;
    $totalNewAllotments = 0;
    $totalErrors = 0;
    
    foreach ($corpIds as $corpId) {
        echo "Processing Corp ID: {$corpId}\n";
        echo "----------------------------------------\n";
        Log::channel('daily')->info("Processing Corp ID: {$corpId}");
        
        try {
            $result = processCorpLeaves($corpId, $year, $month);
            $totalProcessed += $result['processed'];
            $totalSkipped += $result['skipped'];
            $totalNewAllotments += $result['new_allotments'];
            
            $message = "  ✓ Processed: {$result['processed']}, Skipped: {$result['skipped']}, New Allotments: {$result['new_allotments']}";
            echo "{$message}\n\n";
            Log::channel('daily')->info($message);
            
        } catch (\Exception $e) {
            $totalErrors++;
            $errorMessage = "  ✗ Error processing {$corpId}: " . $e->getMessage();
            echo "{$errorMessage}\n\n";
            Log::channel('daily')->error($errorMessage);
        }
    }
    
    // Summary
    echo "======================================\n";
    echo "Summary:\n";
    echo "  Total Processed: {$totalProcessed}\n";
    echo "  Total Skipped: {$totalSkipped}\n";
    echo "  Total New Allotments: {$totalNewAllotments}\n";
    echo "  Total Errors: {$totalErrors}\n";
    echo "======================================\n";
    
    Log::channel('daily')->info("Summary - Processed: {$totalProcessed}, Skipped: {$totalSkipped}, New: {$totalNewAllotments}, Errors: {$totalErrors}");
    Log::channel('daily')->info('Monthly Leave Credits Processing completed successfully');
    
} catch (\Exception $e) {
    Log::channel('daily')->error('Monthly Leave Credits processing failed: ' . $e->getMessage());
    Log::channel('daily')->error($e->getTraceAsString());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Process leave credits for a specific corporation
 */
function processCorpLeaves($corpId, $year, $month)
{
    $processed = 0;
    $skipped = 0;
    $newAllotments = 0;
    
    // Get all employees for this corp_id
    $employees = EmployeeDetail::where('corp_id', $corpId)->get();
    
    if ($employees->isEmpty()) {
        Log::channel('daily')->warning("  No employees found for {$corpId}");
        return ['processed' => 0, 'skipped' => 0, 'new_allotments' => 0];
    }
    
    // Get leave configurations with full details
    $leaveConfigs = getLeaveConfigurations($corpId);
    
    if (empty($leaveConfigs)) {
        Log::channel('daily')->warning("  No completed leave configurations found for {$corpId}");
        return ['processed' => 0, 'skipped' => 0, 'new_allotments' => 0];
    }
    
    DB::beginTransaction();
    
    try {
        foreach ($employees as $employee) {
            $empCode = $employee->EmpCode;
            $empFullName = getEmployeeFullName($employee);
            
            foreach ($leaveConfigs as $config) {
                $basic = $config['basic'];
                $full = $config['full'];
                
                // Check credit type
                $creditType = strtolower($basic->leaveTypeTobeCredited ?? 'yearly');
                
                // Check if leave balance exists for this employee and leave type
                $existingBalance = EmployeeLeaveBalance::where('corp_id', $corpId)
                    ->where('emp_code', $empCode)
                    ->where('leave_type_puid', $basic->puid)
                    ->where('year', $year)
                    ->first();
                
                if (!$existingBalance) {
                    // New employee or first time allotment - create new record
                    $result = createNewLeaveBalance($corpId, $empCode, $empFullName, $basic, $full, $year, $month, $creditType);
                    if ($result) {
                        $newAllotments++;
                    }
                    continue;
                }
                
                // For yearly credited leaves, skip monthly processing
                if ($creditType !== 'monthly') {
                    continue;
                }
                
                // Check if this month was already processed
                if ($existingBalance->month >= $month) {
                    $skipped++;
                    continue;
                }
                
                // Calculate and add monthly credit
                $limitDays = (float)$basic->LimitDays;
                $monthlyCredit = round($limitDays / 12, 2);
                
                // Calculate months to credit
                $lastProcessedMonth = $existingBalance->month ?? 0;
                $monthsToCredit = $month - $lastProcessedMonth;
                
                if ($monthsToCredit <= 0) {
                    $skipped++;
                    continue;
                }
                
                $additionalCredit = $monthlyCredit * $monthsToCredit;
                
                // Check max leave limit based on configuration
                $maxYearlyLimit = (float)$basic->LimitDays;
                
                // Don't exceed yearly limit (excluding carry forward)
                $baseAllotted = $existingBalance->total_allotted - $existingBalance->carry_forward;
                if ($baseAllotted + $additionalCredit > $maxYearlyLimit) {
                    $additionalCredit = max(0, $maxYearlyLimit - $baseAllotted);
                }
                
                if ($additionalCredit > 0) {
                    $existingBalance->update([
                        'total_allotted' => $existingBalance->total_allotted + $additionalCredit,
                        'balance' => $existingBalance->balance + $additionalCredit,
                        'month' => $month,
                        'last_credited_at' => Carbon::now()
                    ]);
                    $processed++;
                } else {
                    $skipped++;
                }
            }
        }
        
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
    
    return [
        'processed' => $processed,
        'skipped' => $skipped,
        'new_allotments' => $newAllotments
    ];
}

/**
 * Get leave configurations with full details
 */
function getLeaveConfigurations($corpId)
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
 * Create new leave balance for employee
 */
function createNewLeaveBalance($corpId, $empCode, $empFullName, $basic, $full, $year, $month, $creditType)
{
    // Calculate carry forward from previous year
    $carryForward = calculateCarryForward($corpId, $empCode, $basic->puid, $year, $full);
    
    // Calculate total allotted based on credit type
    $limitDays = (float)$basic->LimitDays;
    
    if ($creditType === 'monthly') {
        // For monthly, calculate based on months elapsed
        $monthlyCredit = round($limitDays / 12, 2);
        $totalAllotted = $monthlyCredit * $month;
    } else {
        // For yearly, allot full amount
        $totalAllotted = $limitDays;
    }
    
    $totalWithCarryForward = $totalAllotted + $carryForward;
    
    try {
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
            'month' => $creditType === 'monthly' ? $month : null,
            'credit_type' => $creditType,
            'is_lapsed' => false,
            'last_credited_at' => Carbon::now()
        ]);
        return true;
    } catch (\Exception $e) {
        // Duplicate entry - already exists
        return false;
    }
}

/**
 * Calculate carry forward from previous year
 */
function calculateCarryForward($corpId, $empCode, $leaveTypePuid, $year, $full)
{
    $carryForward = 0;
    
    $previousYearBalance = EmployeeLeaveBalance::where('corp_id', $corpId)
        ->where('emp_code', $empCode)
        ->where('leave_type_puid', $leaveTypePuid)
        ->where('year', $year - 1)
        ->first();
    
    if ($previousYearBalance && $full) {
        $lapseLeave = strtolower($full->lapseLeaveYn ?? 'yes');
        
        if ($lapseLeave === 'no') {
            $maxCarryForwardType = strtolower($full->maxCarryForwardLeavesType ?? 'zero');
            $maxCarryForwardBalance = (float)($full->maxCarryForwardLeavesBalance ?? 0);
            
            if ($maxCarryForwardType === 'all') {
                $carryForward = $previousYearBalance->balance;
            } elseif ($maxCarryForwardType === 'days' && $maxCarryForwardBalance > 0) {
                $carryForward = min($previousYearBalance->balance, $maxCarryForwardBalance);
            }
        }
        
        // Mark previous year's leave as lapsed if applicable
        if ($lapseLeave === 'yes' && !$previousYearBalance->is_lapsed) {
            $previousYearBalance->update(['is_lapsed' => true]);
        }
    }
    
    return $carryForward;
}

/**
 * Get employee full name
 */
function getEmployeeFullName($employee)
{
    $fullName = trim($employee->FirstName . ' ' . ($employee->MiddleName ?? '') . ' ' . $employee->LastName);
    return preg_replace('/\s+/', ' ', $fullName);
}
