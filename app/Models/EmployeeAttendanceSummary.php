<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceSummary extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'employee_attendance_summary';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'corpId',
        'empCode',
        'companyName',
        'totalPresent',
        'workingDays',
        'holidays',
        'weekOff',
        'leave',
        'month',
        'year',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'totalPresent' => 'integer',
        'workingDays' => 'integer',
        'holidays' => 'integer',
        'weekOff' => 'integer',
        'leave' => 'double',
    ];
}
