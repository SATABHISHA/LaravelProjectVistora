<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentStageResult extends Model
{
    use HasFactory;

    protected $table = 'recruitment_stage_results';

    protected $fillable = [
        'corp_id', 'application_id', 'stage_id', 'stage_name',
        'scheduled_at', 'conducted_at', 'interviewer_emp_code',
        'interviewer_name', 'remarks', 'rating', 'outcome',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'conducted_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(RecruitmentApplication::class, 'application_id');
    }

    public function stage()
    {
        return $this->belongsTo(RecruitmentStage::class, 'stage_id');
    }
}
