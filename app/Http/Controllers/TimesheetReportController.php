<?php

namespace App\Http\Controllers;

use App\Models\TsDailyReport;
use App\Models\TsKpi;
use App\Models\TsProject;
use App\Models\TsTask;
use App\Models\TsTeamMember;
use App\Models\UserLogin;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimesheetReportController extends Controller
{
    /**
     * Get performance report for a subordinate.
     * Subordinate: own report. Supervisor: own team members. Admin: anyone.
     */
    public function subordinatePerformance(Request $request)
    {
        $user = $request->user();
        $targetUserId = $request->get('target_user_id', $user->user_login_id);
        $period = $request->get('period', now()->format('Y-m')); // YYYY-MM

        // Access control
        if ($user->isSubordinate() && $targetUserId != $user->user_login_id) {
            return response()->json([
                'success' => false,
                'message' => 'Subordinates can only view their own performance.',
            ], 403);
        }

        if ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            if (!in_array($targetUserId, $visibleIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view performance of your own team members.',
                ], 403);
            }
        }

        $targetUser = UserLogin::findOrFail($targetUserId);
        $startDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        // Task Completion Rate
        $totalTasks = TsTask::where('assigned_to', $targetUserId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->count();

        $completedTasks = TsTask::where('assigned_to', $targetUserId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereIn('status', ['completed', 'approved'])
            ->count();

        $approvedTasks = TsTask::where('assigned_to', $targetUserId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->where('status', 'approved')
            ->count();

        $rejectedTasks = TsTask::where('assigned_to', $targetUserId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->where('status', 'rejected')
            ->count();

        $overdueTasks = TsTask::where('assigned_to', $targetUserId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('due_date')
            ->whereNotNull('completed_at')
            ->whereColumn('completed_at', '>', 'due_date')
            ->count();

        $taskCompletionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
        $approvalRate = $completedTasks > 0 ? round(($approvedTasks / $completedTasks) * 100, 2) : 0;

        // Reporting Consistency
        $workingDays = $this->getWorkingDaysInPeriod($startDate, $endDate);
        $daysReported = TsDailyReport::where('user_id', $targetUserId)
            ->where('report_date', '>=', $startDate)
            ->where('report_date', '<=', $endDate)
            ->distinct('report_date')
            ->count('report_date');

        $reportingConsistency = $workingDays > 0 ? round(($daysReported / $workingDays) * 100, 2) : 0;

        // Total hours
        $totalHours = TsDailyReport::where('user_id', $targetUserId)
            ->where('report_date', '>=', $startDate)
            ->where('report_date', '<=', $endDate)
            ->sum('hours_spent');

        $avgHoursPerDay = $daysReported > 0 ? round($totalHours / $daysReported, 2) : 0;

        // On-time completion rate
        $tasksWithDueDate = TsTask::where('assigned_to', $targetUserId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('due_date')
            ->whereIn('status', ['completed', 'approved'])
            ->count();

        $onTimeTasks = TsTask::where('assigned_to', $targetUserId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('due_date')
            ->whereIn('status', ['completed', 'approved'])
            ->whereColumn('completed_at', '<=', 'due_date')
            ->count();

        $onTimeRate = $tasksWithDueDate > 0 ? round(($onTimeTasks / $tasksWithDueDate) * 100, 2) : 0;

        // Save KPIs
        $this->saveKpi($targetUserId, $period, 'task_completion_rate', $taskCompletionRate);
        $this->saveKpi($targetUserId, $period, 'reporting_consistency', $reportingConsistency);
        $this->saveKpi($targetUserId, $period, 'on_time_completion_rate', $onTimeRate);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $targetUser->only(['user_login_id', 'username', 'email_id', 'role']),
                'period' => $period,
                'kpis' => [
                    'task_completion_rate' => $taskCompletionRate,
                    'approval_rate' => $approvalRate,
                    'reporting_consistency' => $reportingConsistency,
                    'on_time_completion_rate' => $onTimeRate,
                ],
                'summary' => [
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'approved_tasks' => $approvedTasks,
                    'rejected_tasks' => $rejectedTasks,
                    'overdue_tasks' => $overdueTasks,
                    'total_hours_logged' => round($totalHours, 2),
                    'avg_hours_per_day' => $avgHoursPerDay,
                    'working_days' => $workingDays,
                    'days_reported' => $daysReported,
                ],
            ],
        ]);
    }

    /**
     * Get performance report for a supervisor.
     * Supervisor: own report. Admin: any supervisor.
     */
    public function supervisorPerformance(Request $request)
    {
        $user = $request->user();
        $targetUserId = $request->get('target_user_id', $user->user_login_id);
        $period = $request->get('period', now()->format('Y-m'));

        $targetUser = UserLogin::findOrFail($targetUserId);

        // Access control
        if ($user->isSupervisor() && $targetUserId != $user->user_login_id) {
            return response()->json([
                'success' => false,
                'message' => 'Supervisors can only view their own performance report.',
            ], 403);
        }

        if (!$targetUser->isSupervisor()) {
            return response()->json([
                'success' => false,
                'message' => 'Specified user is not a supervisor.',
            ], 422);
        }

        $startDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
        $subordinateIds = TsTeamMember::where('supervisor_id', $targetUserId)
            ->pluck('member_id')->toArray();

        // Project Delivery Rate
        $totalProjects = TsProject::where('created_by', $targetUserId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->count();

        $completedProjects = TsProject::where('created_by', $targetUserId)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $startDate)
            ->where('updated_at', '<=', $endDate)
            ->count();

        $projectDeliveryRate = $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100, 2) : 0;

        // On-time delivery
        $onTimeProjects = TsProject::where('created_by', $targetUserId)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $startDate)
            ->where('updated_at', '<=', $endDate)
            ->whereColumn('updated_at', '<=', \DB::raw('COALESCE(extended_end_date, end_date)'))
            ->count();

        $onTimeDeliveryRate = $completedProjects > 0 ? round(($onTimeProjects / $completedProjects) * 100, 2) : 0;

        // Team Productivity
        $teamTotalTasks = TsTask::whereIn('assigned_to', $subordinateIds)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->count();

        $teamCompletedTasks = TsTask::whereIn('assigned_to', $subordinateIds)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereIn('status', ['completed', 'approved'])
            ->count();

        $teamApprovedTasks = TsTask::whereIn('assigned_to', $subordinateIds)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->where('status', 'approved')
            ->count();

        $teamProductivity = $teamTotalTasks > 0 ? round(($teamCompletedTasks / $teamTotalTasks) * 100, 2) : 0;

        // Team hours
        $teamTotalHours = TsDailyReport::whereIn('user_id', $subordinateIds)
            ->where('report_date', '>=', $startDate)
            ->where('report_date', '<=', $endDate)
            ->sum('hours_spent');

        // Avg task approval time (in days)
        $avgApprovalTime = TsTask::whereIn('assigned_to', $subordinateIds)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->whereNotNull('completed_at')
            ->where('approved_at', '>=', $startDate)
            ->where('approved_at', '<=', $endDate)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, completed_at, approved_at)) as avg_hours')
            ->value('avg_hours');

        $avgApprovalTimeDays = $avgApprovalTime ? round($avgApprovalTime / 24, 2) : 0;

        // Save KPIs
        $this->saveKpi($targetUserId, $period, 'project_delivery_rate', $projectDeliveryRate);
        $this->saveKpi($targetUserId, $period, 'team_productivity', $teamProductivity);
        $this->saveKpi($targetUserId, $period, 'on_time_delivery_rate', $onTimeDeliveryRate);

        // Per subordinate breakdown
        $subordinateBreakdown = [];
        foreach ($subordinateIds as $subId) {
            $sub = UserLogin::find($subId);
            if (!$sub) continue;
            $subTotal = TsTask::where('assigned_to', $subId)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->count();
            $subCompleted = TsTask::where('assigned_to', $subId)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->whereIn('status', ['completed', 'approved'])
                ->count();
            $subHours = TsDailyReport::where('user_id', $subId)
                ->where('report_date', '>=', $startDate)
                ->where('report_date', '<=', $endDate)
                ->sum('hours_spent');

            $subordinateBreakdown[] = [
                'user' => $sub->only(['user_login_id', 'username', 'email_id']),
                'total_tasks' => $subTotal,
                'completed_tasks' => $subCompleted,
                'completion_rate' => $subTotal > 0 ? round(($subCompleted / $subTotal) * 100, 2) : 0,
                'hours_logged' => round($subHours, 2),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'supervisor' => $targetUser->only(['user_login_id', 'username', 'email_id', 'role']),
                'period' => $period,
                'kpis' => [
                    'project_delivery_rate' => $projectDeliveryRate,
                    'on_time_delivery_rate' => $onTimeDeliveryRate,
                    'team_productivity' => $teamProductivity,
                ],
                'summary' => [
                    'total_projects' => $totalProjects,
                    'completed_projects' => $completedProjects,
                    'total_subordinates' => count($subordinateIds),
                    'team_total_tasks' => $teamTotalTasks,
                    'team_completed_tasks' => $teamCompletedTasks,
                    'team_approved_tasks' => $teamApprovedTasks,
                    'team_total_hours' => round($teamTotalHours, 2),
                    'avg_approval_time_days' => $avgApprovalTimeDays,
                ],
                'subordinate_breakdown' => $subordinateBreakdown,
            ],
        ]);
    }

    /**
     * Admin-only: Organization-wide efficiency report.
     */
    public function organizationPerformance(Request $request)
    {
        $user = $request->user();
        $period = $request->get('period', now()->format('Y-m'));
        $startDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        // Overall task metrics
        $totalTasks = TsTask::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->count();

        $completedTasks = TsTask::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereIn('status', ['completed', 'approved'])
            ->count();

        $approvedTasks = TsTask::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->where('status', 'approved')
            ->count();

        $overdueTasks = TsTask::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('due_date')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('completed_at')
                        ->whereColumn('completed_at', '>', 'due_date');
                })->orWhere(function ($q2) {
                    $q2->whereNull('completed_at')
                        ->where('due_date', '<', now())
                        ->whereNotIn('status', ['completed', 'approved']);
                });
            })
            ->count();

        $orgEfficiency = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        // Overall project metrics
        $totalProjects = TsProject::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->count();

        $completedProjects = TsProject::where('status', 'completed')
            ->where('updated_at', '>=', $startDate)
            ->where('updated_at', '<=', $endDate)
            ->count();

        // Total hours across org
        $totalHours = TsDailyReport::where('report_date', '>=', $startDate)
            ->where('report_date', '<=', $endDate)
            ->sum('hours_spent');

        // Total active users in corp
        $activeSubordinates = UserLogin::where('corp_id', $user->corp_id)
            ->where('active_yn', 1)
            ->where('admin_yn', '!=', 1)
            ->where('supervisor_yn', '!=', 1)
            ->count();

        $activeSupervisors = UserLogin::where('corp_id', $user->corp_id)
            ->where('active_yn', 1)
            ->where('supervisor_yn', 1)
            ->where('admin_yn', '!=', 1)
            ->count();

        // Supervisor comparisons
        $supervisorIds = UserLogin::where('corp_id', $user->corp_id)
            ->where('active_yn', 1)
            ->where('supervisor_yn', 1)
            ->where('admin_yn', '!=', 1)
            ->pluck('user_login_id');

        $supervisorComparisons = [];

        foreach ($supervisorIds as $supId) {
            $sup = UserLogin::find($supId);
            $subIds = TsTeamMember::where('supervisor_id', $supId)->pluck('member_id')->toArray();
            $supTotalTasks = TsTask::whereIn('assigned_to', $subIds)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->count();
            $supCompletedTasks = TsTask::whereIn('assigned_to', $subIds)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->whereIn('status', ['completed', 'approved'])
                ->count();
            $supProjects = TsProject::where('created_by', $supId)->count();
            $supCompletedProjects = TsProject::where('created_by', $supId)->where('status', 'completed')->count();
            $supHours = TsDailyReport::whereIn('user_id', $subIds)
                ->where('report_date', '>=', $startDate)
                ->where('report_date', '<=', $endDate)
                ->sum('hours_spent');

            $supervisorComparisons[] = [
                'supervisor' => $sup->only(['user_login_id', 'username', 'email_id']),
                'subordinate_count' => count($subIds),
                'total_tasks' => $supTotalTasks,
                'completed_tasks' => $supCompletedTasks,
                'team_productivity' => $supTotalTasks > 0 ? round(($supCompletedTasks / $supTotalTasks) * 100, 2) : 0,
                'total_projects' => $supProjects,
                'completed_projects' => $supCompletedProjects,
                'team_hours_logged' => round($supHours, 2),
            ];
        }

        // Sort by team_productivity descending
        usort($supervisorComparisons, function ($a, $b) {
            return $b['team_productivity'] <=> $a['team_productivity'];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'kpis' => [
                    'organization_efficiency' => $orgEfficiency,
                    'task_completion_rate' => $orgEfficiency,
                    'project_completion_rate' => $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100, 2) : 0,
                ],
                'summary' => [
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'approved_tasks' => $approvedTasks,
                    'overdue_tasks' => $overdueTasks,
                    'total_projects' => $totalProjects,
                    'completed_projects' => $completedProjects,
                    'total_hours_logged' => round($totalHours, 2),
                    'active_subordinates' => $activeSubordinates,
                    'active_supervisors' => $activeSupervisors,
                ],
                'supervisor_comparisons' => $supervisorComparisons,
            ],
        ]);
    }

    /**
     * Get KPI history for a user.
     */
    public function kpiHistory(Request $request)
    {
        $user = $request->user();
        $targetUserId = $request->get('target_user_id', $user->user_login_id);

        // Access control
        if ($user->isSubordinate() && $targetUserId != $user->user_login_id) {
            return response()->json([
                'success' => false,
                'message' => 'Subordinates can only view their own KPI history.',
            ], 403);
        }

        if ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            if (!in_array($targetUserId, $visibleIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.',
                ], 403);
            }
        }

        $query = TsKpi::where('user_id', $targetUserId);

        if ($request->has('metric_name')) {
            $query->where('metric_name', $request->metric_name);
        }

        $kpis = $query->orderBy('period', 'desc')
            ->orderBy('metric_name')
            ->paginate($request->get('per_page', 30));

        return response()->json([
            'success' => true,
            'data' => $kpis,
        ]);
    }

    /**
     * Helper: Save/update a KPI record.
     */
    private function saveKpi(int $userId, string $period, string $metricName, float $value): void
    {
        TsKpi::updateOrCreate(
            [
                'user_id' => $userId,
                'period' => $period,
                'metric_name' => $metricName,
            ],
            [
                'metric_value' => $value,
                'calculated_at' => now(),
            ]
        );
    }

    /**
     * Helper: Get approximate working days (Mon-Fri) in a period.
     */
    private function getWorkingDaysInPeriod(Carbon $start, Carbon $end): int
    {
        $count = 0;
        $current = $start->copy();
        $today = now()->endOfDay();

        while ($current <= $end && $current <= $today) {
            if ($current->isWeekday()) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }
}