<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConditionalWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'workflow_name', 'request_type',
        'workflow_recruitment_yn', 'workflow_workforce_yn', 'workflow_officetime_yn',
        'workflow_payroll_yn', 'workflow_expense_yn', 'workflow_performance_yn',
        'workflow_asset_yn', 'condition_type', 'operation_type', 'value',
        'role_name', 'intimationYn', 'due_day', 'turaround_time'
    ];
}
