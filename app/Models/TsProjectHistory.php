<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TsProjectHistory extends Model
{
    public $timestamps = false;

    protected $table = 'ts_project_histories';

    protected $fillable = [
        'project_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'remarks',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(TsProject::class, 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(TsUser::class, 'user_id');
    }
}
