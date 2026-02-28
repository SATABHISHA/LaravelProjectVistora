<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLogin extends Model
{
    use HasFactory;

    protected $table = 'userlogin';
    protected $primaryKey = 'user_login_id';

    protected $fillable = [
        'corp_id', 'email_id', 'username', 'password',
        'empcode',
        'company_name', 'active_yn', 'admin_yn', 'supervisor_yn'
    ];

    protected $hidden = ['password'];

    protected $appends = ['role', 'is_active'];

    // ================================================================
    // Computed Accessors (for Timesheet module)
    // ================================================================

    /**
     * Compute role from admin_yn / supervisor_yn flags.
     * admin_yn=1 → admin, supervisor_yn=1 → supervisor, else → subordinate
     */
    public function getRoleAttribute(): string
    {
        if ((int) $this->admin_yn === 1) {
            return 'admin';
        }
        if ((int) $this->supervisor_yn === 1) {
            return 'supervisor';
        }
        return 'subordinate';
    }

    /**
     * Compute is_active from active_yn flag.
     */
    public function getIsActiveAttribute(): bool
    {
        return (int) $this->active_yn === 1;
    }

    // ================================================================
    // Role Check Helpers
    // ================================================================

    public function isAdmin(): bool
    {
        return (int) $this->admin_yn === 1;
    }

    public function isSupervisor(): bool
    {
        return (int) $this->supervisor_yn === 1 && (int) $this->admin_yn !== 1;
    }

    public function isSubordinate(): bool
    {
        return !$this->isAdmin() && !$this->isSupervisor();
    }

    // ================================================================
    // Team Member Relationships (Timesheet)
    // ================================================================

    /**
     * Get subordinates assigned to this supervisor via ts_team_members.
     */
    public function subordinates()
    {
        return $this->belongsToMany(
            UserLogin::class,
            'ts_team_members',
            'supervisor_id',
            'member_id',
            'user_login_id',
            'user_login_id'
        );
    }

    /**
     * Get supervisors of this user via ts_team_members.
     */
    public function supervisorUsers()
    {
        return $this->belongsToMany(
            UserLogin::class,
            'ts_team_members',
            'member_id',
            'supervisor_id',
            'user_login_id',
            'user_login_id'
        );
    }

    /**
     * Check if a given user is a team member of this supervisor.
     */
    public function isMyTeamMember(int $userId): bool
    {
        return TsTeamMember::where('supervisor_id', $this->user_login_id)
            ->where('member_id', $userId)
            ->exists();
    }

    /**
     * Get all subordinate IDs visible to this user for timesheet.
     */
    public function getVisibleSubordinateIds(): array
    {
        if ($this->isAdmin()) {
            return self::where('corp_id', $this->corp_id)
                ->where('admin_yn', '!=', 1)
                ->where('supervisor_yn', '!=', 1)
                ->where('user_login_id', '!=', $this->user_login_id)
                ->pluck('user_login_id')
                ->toArray();
        }

        if ($this->isSupervisor()) {
            return TsTeamMember::where('supervisor_id', $this->user_login_id)
                ->pluck('member_id')
                ->toArray();
        }

        return [$this->user_login_id];
    }

    /**
     * Get all user IDs visible to this user (including self) for timesheet.
     */
    public function getVisibleUserIds(): array
    {
        if ($this->isAdmin()) {
            return self::where('corp_id', $this->corp_id)
                ->pluck('user_login_id')
                ->toArray();
        }

        if ($this->isSupervisor()) {
            $ids = TsTeamMember::where('supervisor_id', $this->user_login_id)
                ->pluck('member_id')
                ->toArray();
            $ids[] = $this->user_login_id;
            return $ids;
        }

        return [$this->user_login_id];
    }

    // ================================================================
    // Timesheet Relationships
    // ================================================================

    public function tsCreatedProjects()
    {
        return $this->hasMany(TsProject::class, 'created_by', 'user_login_id');
    }

    public function tsAssignedTasks()
    {
        return $this->hasMany(TsTask::class, 'assigned_to', 'user_login_id');
    }

    public function tsDailyReports()
    {
        return $this->hasMany(TsDailyReport::class, 'user_id', 'user_login_id');
    }

    public function tsKpis()
    {
        return $this->hasMany(TsKpi::class, 'user_id', 'user_login_id');
    }
}
