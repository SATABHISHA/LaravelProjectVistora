<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TsTaskHistory extends Model
{
    public $timestamps = false;

    protected $table = 'ts_task_histories';

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'remarks',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(TsTask::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(UserLogin::class, 'user_id', 'user_login_id');
    }
}
