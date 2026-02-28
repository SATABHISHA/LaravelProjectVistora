<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TsTask extends Model
{
    use HasFactory;

    protected $table = 'ts_tasks';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'assigned_to',
        'assigned_by',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(TsProject::class, 'project_id');
    }

    public function assignee()
    {
        return $this->belongsTo(UserLogin::class, 'assigned_to', 'user_login_id');
    }

    public function assigner()
    {
        return $this->belongsTo(UserLogin::class, 'assigned_by', 'user_login_id');
    }

    public function approver()
    {
        return $this->belongsTo(UserLogin::class, 'approved_by', 'user_login_id');
    }

    public function dailyReports()
    {
        return $this->hasMany(TsDailyReport::class, 'task_id');
    }

    public function histories()
    {
        return $this->hasMany(TsTaskHistory::class, 'task_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'approved']);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && now()->greaterThan($this->due_date)
            && !in_array($this->status, ['completed', 'approved']);
    }
}
