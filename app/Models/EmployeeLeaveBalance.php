<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $table = 'employee_leave_balances';

    protected $fillable = [
        'corp_id',
        'emp_code',
        'emp_full_name',
        'leave_type_puid',
        'leave_code',
        'leave_name',
        'total_allotted',
        'used',
        'balance',
        'carry_forward',
        'year',
        'month',
        'credit_type',
        'is_lapsed',
        'last_credited_at'
    ];

    protected $casts = [
        'total_allotted' => 'decimal:2',
        'used' => 'decimal:2',
        'balance' => 'decimal:2',
        'carry_forward' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer',
        'is_lapsed' => 'boolean',
        'last_credited_at' => 'datetime'
    ];

    // Relationship with leave type basic configuration
    public function leaveTypeConfig()
    {
        return $this->belongsTo(LeaveTypeBasicConfiguration::class, 'leave_type_puid', 'puid');
    }

    // Relationship with employee details
    public function employee()
    {
        return $this->belongsTo(EmployeeDetail::class, 'emp_code', 'EmpCode')
            ->where('corp_id', $this->corp_id);
    }
}
