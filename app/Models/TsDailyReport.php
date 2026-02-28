<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TsDailyReport extends Model
{
    use HasFactory;

    protected $table = 'ts_daily_reports';

    protected $fillable = [
        'user_id',
        'task_id',
        'report_date',
        'description',
        'hours_spent',
    ];

    protected $casts = [
        'report_date' => 'date',
        'hours_spent' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(TsUser::class, 'user_id');
    }

    public function task()
    {
        return $this->belongsTo(TsTask::class, 'task_id');
    }
}
