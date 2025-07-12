<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approver extends Model
{
    use HasFactory;

    protected $fillable = [
        'puid',
        'corp_id', 'workflow_name', 'request_type',
        'workflow_recruitment_yn', 'workflow_workforce_yn', 'workflow_officetime_yn',
        'workflow_payroll_yn', 'workflow_expense_yn', 'workflow_performance_yn',
        'workflow_asset_yn', 'approver', 'intimationYN', 'due_day', 'turnaround_time', 'active_yn'
    ];
}
