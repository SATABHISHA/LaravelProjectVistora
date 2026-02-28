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
        return $this->belongsTo(UserLogin::class, 'user_id', 'user_login_id');
    }

    public function assigner()
    {
        return $this->belongsTo(UserLogin::class, 'assigned_by', 'user_login_id');
    }
}
