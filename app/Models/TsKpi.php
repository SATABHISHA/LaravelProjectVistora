<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TsKpi extends Model
{
    use HasFactory;

    protected $table = 'ts_kpis';

    protected $fillable = [
        'user_id',
        'period',
        'metric_name',
        'metric_value',
        'calculated_at',
    ];

    protected $casts = [
        'metric_value' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(UserLogin::class, 'user_id', 'user_login_id');
    }
}
