<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowAutomation extends Model
{
    use HasFactory;

    protected $table = 'workflow_automation'; // <-- Add this line

    protected $fillable = [
        'corp_id',
        'workflow_recruitment_yn',
        'workflow_workforce_yn',
        'workflow_officetime_yn',
        'workflow_payroll_yn',
        'workflow_expense_yn',
        'workflow_performance_yn',
        'workflow_asset_yn',
        'workflow_name',
        'description',
        'request_type',
        'flow_type',
        'applicability',
        'advance_applicability',
        'from_days',
        'to_days',
        'conditional_workflowYN',
    ];
}
