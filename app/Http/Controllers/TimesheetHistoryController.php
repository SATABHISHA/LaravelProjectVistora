<?php

namespace App\Http\Controllers;

use App\Models\TsProjectHistory;
use App\Models\TsTaskHistory;
use App\Models\UserLogin;
use Illuminate\Http\Request;

class TimesheetHistoryController extends Controller
{
    /**
     * Get task histories with role-based visibility.
     */
    public function taskHistories(Request $request)
    {
        $user = $request->user();
        $query = TsTaskHistory::with(['task:id,title,project_id,assigned_to', 'user']);

        if ($user->isAdmin()) {
            // Admin sees all
        } elseif ($user->isSupervisor()) {
            $visibleIds = $user->getVisibleUserIds();
            $query->whereHas('task', function ($q) use ($visibleIds, $user) {
                $q->whereIn('assigned_to', $visibleIds)
                  ->orWhere('assigned_by', $user->user_login_id);
            });
        } else {
            $query->whereHas('task', function ($q) use ($user) {
                $q->where('assigned_to', $user->user_login_id);
            });
        }

        // Filters
        if ($request->has('task_id')) {
            $query->where('task_id', $request->task_id);
        }
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('action_user_id')) {
            $query->where('user_id', $request->action_user_id);
        }
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $histories = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $histories,
        ]);
    }

    /**
     * Get project histories with role-based visibility.
     */
    public function projectHistories(Request $request)
    {
        $user = $request->user();
        $query = TsProjectHistory::with(['project:id,name,status,created_by', 'user']);

        if ($user->isAdmin()) {
            // Admin sees all
        } elseif ($user->isSupervisor()) {
            $subordinateIds = $user->getVisibleSubordinateIds();
            $query->whereHas('project', function ($q) use ($user, $subordinateIds) {
                $q->where('created_by', $user->user_login_id)
                  ->orWhereHas('members', function ($q2) use ($subordinateIds) {
                      $q2->whereIn('user_id', $subordinateIds);
                  });
            });
        } else {
            $query->whereHas('project.members', function ($q) use ($user) {
                $q->where('user_id', $user->user_login_id);
            });
        }

        // Filters
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $histories = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $histories,
        ]);
    }
}
