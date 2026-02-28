<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TsProjectAssignment extends Model
{
    use HasFactory;

    protected $table = 'ts_project_assignments';

    protected $fillable = [
        'project_id',
        'user_id',
        'assigned_by',
    ];

    public function project()
    {
        return $this->belongsTo(TsProject::class, 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(TsUser::class, 'user_id');
    }

    public function assigner()
    {
        return $this->belongsTo(TsUser::class, 'assigned_by');
    }
}
