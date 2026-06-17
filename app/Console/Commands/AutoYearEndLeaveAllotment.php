<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use App\Models\LeaveYearEndPreference;
use App\Http\Controllers\EmployeeLeaveBalanceApiController;
use Carbon\Carbon;

class AutoYearEndLeaveAllotment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:auto-year-end-allot
                            {--year= : Target year to allot (default: current year)}
                            {--corp_id= : Process preferences for a specific corp_id only}
                            {--force : Run even when not Jan 1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-process year-end leave allotments for companies with auto mode enabled';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now('Asia/Kolkata');
        $forcedYear = $this->option('year');
        $force = (bool) $this->option('force');

        if (!$forcedYear && !$force && !($now->month === 1 && $now->day === 1)) {
            $this->info('Skipping: auto year-end allotment runs on Jan 1 only. Use --force or --year for manual execution.');
            return self::SUCCESS;
        }

        $toYear = $forcedYear ? (int) $forcedYear : (int) $now->year;
        $fromYear = $toYear - 1;

        if (!Schema::hasTable('leave_year_end_preferences')) {
            $this->warn('Skipping: leave_year_end_preferences table is not available yet.');
            return self::SUCCESS;
        }

        $prefsQuery = LeaveYearEndPreference::where('auto_allot_enabled', true);
        if ($this->option('corp_id')) {
            $prefsQuery->where('corp_id', $this->option('corp_id'));
        }

        $preferences = $prefsQuery->get();

        if ($preferences->isEmpty()) {
            $this->info('No enabled auto year-end preferences found.');
            return self::SUCCESS;
        }

        $controller = app(EmployeeLeaveBalanceApiController::class);

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($preferences as $pref) {
            if ((int) ($pref->last_run_year ?? 0) >= $toYear) {
                $this->line("Skipping {$pref->corp_id}/{$pref->company_name}: already processed for {$toYear}.");
                $skipped++;
                continue;
            }

            try {
                $result = $controller->runYearEndAllotmentForCompany(
                    $pref->corp_id,
                    $pref->company_name,
                    $fromYear,
                    $toYear
                );

                $pref->last_run_year = $toYear;
                $pref->save();

                $this->info("Processed {$pref->corp_id}/{$pref->company_name}: created={$result['total_leave_records_created']}, updated={$result['total_leave_records_updated']}, skipped={$result['total_records_skipped']}");
                $processed++;
            } catch (\Throwable $e) {
                $this->error("Failed {$pref->corp_id}/{$pref->company_name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Processed={$processed}, Skipped={$skipped}, Failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
