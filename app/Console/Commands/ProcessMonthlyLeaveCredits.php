<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveTypeBasicConfiguration;
use App\Models\LeaveTypeFullConfiguration;
use App\Models\EmployeeDetail;
use App\Models\CorporateId;
use Carbon\Carbon;

class ProcessMonthlyLeaveCredits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:process-monthly-credits 
                            {--corp_id= : Process for specific corp_id only}
                            {--year= : Process for specific year (default: current year)}
                            {--month= : Process for specific month (default: current month)}
                            {--force : Force reprocess even if already processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process monthly leave credits for all corporations based on leave configurations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificCorpId = $this->option('corp_id');
        $year = $this->option('year') ?? Carbon::now()->year;
        $month = $this->option('month') ?? Carbon::now()->month;
        $force = $this->option('force');

        $this->info("======================================");
        $this->info("Processing Monthly Leave Credits");
        $this->info("Year: {$year}, Month: {$month}");
        $this->info("======================================");

        // Get all corporate IDs or specific one
        if ($specificCorpId) {
            $corpIds = [$specificCorpId];
        } else {
            // Get unique corp_ids from leave configurations
            $corpIds = LeaveTypeBasicConfiguration::distinct()
                ->pluck('corpid')
                ->toArray();
        }

        if (empty($corpIds)) {
            $this->warn("No corporate IDs found with leave configurations.");
            return 0;
        }

        $totalProcessed = 0;
        $totalSkipped = 0;
        $totalNewAllotments = 0;
        $totalErrors = 0;

        foreach ($corpIds as $corpId) {
            $this->info("\nProcessing Corp ID: {$corpId}");
            $this->line("----------------------------------------");

            try {
                $result = $this->processCorpLeaves($corpId, $year, $month, $force);
                $totalProcessed += $result['processed'];
                $totalSkipped += $result['skipped'];
                $totalNewAllotments += $result['new_allotments'];
                
                $this->info("  ✓ Processed: {$result['processed']}, Skipped: {$result['skipped']}, New Allotments: {$result['new_allotments']}");
            } catch (\Exception $e) {
                $totalErrors++;
                $this->error("  ✗ Error processing {$corpId}: " . $e->getMessage());
            }
        }

        $this->info("\n======================================");
        $this->info("Summary:");
        $this->info("  Total Processed: {$totalProcessed}");
        $this->info("  Total Skipped: {$totalSkipped}");
        $this->info("  Total New Allotments: {$totalNewAllotments}");
        $this->info("  Total Errors: {$totalErrors}");
        $this->info("======================================");

        // Log the execution
        $this->logExecution($year, $month, $totalProcessed, $totalSkipped, $totalNewAllotments, $totalErrors);

        return 0;
    }

    /**
     * Process leave credits for a specific corporation
     */
    private function processCorpLeaves($corpId, $year, $month, $force)
    {
        $processed = 0;
        $skipped = 0;
        $newAllotments = 0;

        // Get all employees for this corp_id
        $employees = EmployeeDetail::where('corp_id', $corpId)->get();

        if ($employees->isEmpty()) {
            $this->warn("  No employees found for {$corpId}");
            return ['processed' => 0, 'skipped' => 0, 'new_allotments' => 0];
        }

        // Get leave configurations with full details
        $leaveConfigs = $this->getLeaveConfigurations($corpId);

        if (empty($leaveConfigs)) {
            $this->warn("  No completed leave configurations found for {$corpId}");
            return ['processed' => 0, 'skipped' => 0, 'new_allotments' => 0];
        }

        DB::beginTransaction();

        try {
            foreach ($employees as $employee) {
                $empCode = $employee->EmpCode;
                $empFullName = $this->getEmployeeFullName($employee);

                foreach ($leaveConfigs as $config) {
                    $basic = $config['basic'];
                    $full = $config['full'];

                    // Check credit type - only process monthly credited leaves
                    $creditType = strtolower($basic->leaveTypeTobeCredited ?? 'yearly');
                    
                    // Check if leave balance exists for this employee and leave type
                    $existingBalance = EmployeeLeaveBalance::where('corp_id', $corpId)
                        ->where('emp_code', $empCode)
                        ->where('leave_type_puid', $basic->puid)
                        ->where('year', $year)
                        ->first();

                    if (!$existingBalance) {
                        // New employee or first time allotment - create new record
                        $result = $this->createNewLeaveBalance($corpId, $empCode, $empFullName, $basic, $full, $year, $month, $creditType);
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
                    if (!$force && $existingBalance->month >= $month) {
                        $skipped++;
                        continue;
                    }

                    // Calculate and add monthly credit
                    $limitDays = (float)$basic->LimitDays;
                    $monthlyCredit = round($limitDays / 12, 2);
                    
                    // Calculate months to credit
                    $lastProcessedMonth = $existingBalance->month ?? 0;
                    $monthsToCredit = $month - $lastProcessedMonth;
                    
                    if ($monthsToCredit <= 0 && !$force) {
                        $skipped++;
                        continue;
                    }

                    $additionalCredit = $monthlyCredit * max(1, $monthsToCredit);

                    // Check max leave limit based on configuration
                    $maxYearlyLimit = (float)$basic->LimitDays;
                    $newTotal = $existingBalance->total_allotted + $additionalCredit;
                    
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
     * Create new leave balance for employee
     */
    private function createNewLeaveBalance($corpId, $empCode, $empFullName, $basic, $full, $year, $month, $creditType)
    {
        // Calculate carry forward from previous year
        $carryForward = $this->calculateCarryForward($corpId, $empCode, $basic->puid, $year, $full);

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
    private function calculateCarryForward($corpId, $empCode, $leaveTypePuid, $year, $full)
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
     * Get leave configurations with full details
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
     * Get employee full name
     */
    private function getEmployeeFullName($employee)
    {
        $fullName = trim($employee->FirstName . ' ' . ($employee->MiddleName ?? '') . ' ' . $employee->LastName);
        return preg_replace('/\s+/', ' ', $fullName);
    }

    /**
     * Log the execution details
     */
    private function logExecution($year, $month, $processed, $skipped, $newAllotments, $errors)
    {
        $logMessage = sprintf(
            "[%s] Leave Credit Cron - Year: %d, Month: %d, Processed: %d, Skipped: %d, New: %d, Errors: %d",
            Carbon::now()->toDateTimeString(),
            $year,
            $month,
            $processed,
            $skipped,
            $newAllotments,
            $errors
        );

        \Log::channel('daily')->info($logMessage);
    }
}
