<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TsUser extends Authenticatable
{
    use HasFactory;

    protected $table = 'ts_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'supervisor_id',
        'is_active',
        'corp_id',
        'vistora_user_login_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // Relationships
    public function supervisor()
    {
        return $this->belongsTo(TsUser::class, 'supervisor_id');
    }

    public function subordinates()
    {
        return $this->hasMany(TsUser::class, 'supervisor_id');
    }

    public function createdProjects()
    {
        return $this->hasMany(TsProject::class, 'created_by');
    }

    public function projectAssignments()
    {
        return $this->hasMany(TsProjectAssignment::class, 'user_id');
    }

    public function assignedProjects()
    {
        return $this->belongsToMany(TsProject::class, 'ts_project_assignments', 'user_id', 'project_id')
            ->withTimestamps();
    }

    public function assignedTasks()
    {
        return $this->hasMany(TsTask::class, 'assigned_to');
    }

    public function createdTasks()
    {
        return $this->hasMany(TsTask::class, 'assigned_by');
    }

    public function dailyReports()
    {
        return $this->hasMany(TsDailyReport::class, 'user_id');
    }

    public function kpis()
    {
        return $this->hasMany(TsKpi::class, 'user_id');
    }

    // Scopes
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeSupervisors($query)
    {
        return $query->where('role', 'supervisor');
    }

    public function scopeSubordinates($query)
    {
        return $query->where('role', 'subordinate');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isSubordinate(): bool
    {
        return $this->role === 'subordinate';
    }

    /**
     * Get all subordinate IDs visible to this user.
     */
    public function getVisibleSubordinateIds(): array
    {
        if ($this->isAdmin()) {
            return TsUser::where('role', 'subordinate')->pluck('id')->toArray();
        }

        if ($this->isSupervisor()) {
            return $this->subordinates()->pluck('id')->toArray();
        }

        return [$this->id];
    }

    /**
     * Get all user IDs visible to this user (including self).
     */
    public function getVisibleUserIds(): array
    {
        if ($this->isAdmin()) {
            return TsUser::pluck('id')->toArray();
        }

        if ($this->isSupervisor()) {
            $ids = $this->subordinates()->pluck('id')->toArray();
            $ids[] = $this->id;
            return $ids;
        }

        return [$this->id];
    }
}
