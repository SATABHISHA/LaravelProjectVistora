<?php

namespace App\Http\Controllers;

use App\Models\TsDailyReport;
use App\Models\TsTask;
use App\Models\TsUser;
use Illuminate\Http\Request;

class TimesheetDailyReportController extends Controller
{
    /**
     * List daily reports with role-based visibility.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = TsDailyReport::with([
            'user:id,name,email,role',
            'task:id,title,project_id,status',
        ]);

        if ($user->isAdmin()) {
            // Admin sees all reports
        } elseif ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            $query->whereIn('user_id', $visibleIds);
        } else {
            $query->where('user_id', $user->id);
        }

        // Filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('task_id')) {
            $query->where('task_id', $request->task_id);
        }
        if ($request->has('date_from')) {
            $query->where('report_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('report_date', '<=', $request->date_to);
        }
        if ($request->has('report_date')) {
            $query->where('report_date', $request->report_date);
        }

        $reports = $query->orderBy('report_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Submit a daily report. Subordinate only.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'task_id' => 'nullable|exists:ts_tasks,id',
            'report_date' => 'required|date|before_or_equal:today',
            'description' => 'required|string',
            'hours_spent' => 'required|numeric|min:0.25|max:24',
        ]);

        // If task_id is provided, verify it's assigned to this user
        if ($request->task_id) {
            $task = TsTask::where('id', $request->task_id)
                ->where('assigned_to', $user->id)
                ->first();

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or not assigned to you.',
                ], 404);
            }
        }

        // Check for duplicate
        $exists = TsDailyReport::where('user_id', $user->id)
            ->where('task_id', $request->task_id)
            ->where('report_date', $request->report_date)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A report already exists for this task on this date.',
            ], 422);
        }

        $report = TsDailyReport::create([
            'user_id' => $user->id,
            'task_id' => $request->task_id,
            'report_date' => $request->report_date,
            'description' => $request->description,
            'hours_spent' => $request->hours_spent,
        ]);

        $report->load(['user:id,name,email', 'task:id,title,project_id']);

        return response()->json([
            'success' => true,
            'message' => 'Daily report submitted successfully.',
            'data' => $report,
        ], 201);
    }

    /**
     * Show a single daily report.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $report = TsDailyReport::with([
            'user:id,name,email,role',
            'task:id,title,project_id,status',
        ])->findOrFail($id);

        if (!$this->canViewReport($user, $report)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Update a daily report. Subordinate (own report) only.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $report = TsDailyReport::findOrFail($id);

        if ($report->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own reports.',
            ], 403);
        }

        $request->validate([
            'description' => 'sometimes|string',
            'hours_spent' => 'sometimes|numeric|min:0.25|max:24',
        ]);

        $report->update($request->only(['description', 'hours_spent']));
        $report->load(['user:id,name,email', 'task:id,title,project_id']);

        return response()->json([
            'success' => true,
            'message' => 'Daily report updated successfully.',
            'data' => $report,
        ]);
    }

    /**
     * Delete a daily report. Subordinate (own) or Admin.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $report = TsDailyReport::findOrFail($id);

        if (!$user->isAdmin() && $report->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.',
            ], 403);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Daily report deleted successfully.',
        ]);
    }

    private function canViewReport(TsUser $user, TsDailyReport $report): bool
    {
        if ($user->isAdmin()) return true;

        if ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            return in_array($report->user_id, $visibleIds);
        }

        return $report->user_id === $user->id;
    }
}
