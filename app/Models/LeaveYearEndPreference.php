<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveYearEndPreference extends Model
{
    use HasFactory;

    protected $table = 'leave_year_end_preferences';

    protected $fillable = [
        'corp_id',
        'company_name',
        'auto_allot_enabled',
        'timezone',
        'last_run_year',
    ];

    protected $casts = [
        'auto_allot_enabled' => 'boolean',
        'last_run_year' => 'integer',
    ];
}
