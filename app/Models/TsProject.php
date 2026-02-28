<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TsProject extends Model
{
    use HasFactory;

    protected $table = 'ts_projects';

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'start_date',
        'end_date',
        'extended_end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'extended_end_date' => 'date',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(UserLogin::class, 'created_by', 'user_login_id');
    }

    public function assignments()
    {
        return $this->hasMany(TsProjectAssignment::class, 'project_id');
    }

    public function members()
    {
        return $this->belongsToMany(UserLogin::class, 'ts_project_assignments', 'project_id', 'user_id', 'id', 'user_login_id')
            ->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(TsTask::class, 'project_id');
    }

    public function histories()
    {
        return $this->hasMany(TsProjectHistory::class, 'project_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get the effective end date (extended or original).
     */
    public function getEffectiveEndDateAttribute()
    {
        return $this->extended_end_date ?? $this->end_date;
    }

    /**
     * Check if the project is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'active' && now()->greaterThan($this->effective_end_date);
    }
}
