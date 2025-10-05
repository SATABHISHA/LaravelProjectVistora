<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'leave_request';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'puid',
        'corp_id',
        'company_name',
        'empcode',
        'full_name',
        'emp_designation',
        'from_date',
        'to_date',
        'reason',
        'leave_reason_description',
        'approved_reject_return_by',
        'reject_reason',
        'status',
    ];
}
