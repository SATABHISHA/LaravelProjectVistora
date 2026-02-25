<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentApplication extends Model
{
    use HasFactory;

    protected $table = 'recruitment_applications';

    protected $fillable = [
        'corp_id', 'job_posting_id', 'candidate_id', 'applied_date',
        'current_stage', 'status', 'overall_remarks',
        'final_decision', 'decided_by', 'decision_date',
    ];

    protected $casts = [
        'decision_date' => 'datetime',
    ];

    public function jobPosting()
    {
        return $this->belongsTo(RecruitmentJobPosting::class, 'job_posting_id');
    }

    public function candidate()
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    public function stageResults()
    {
        return $this->hasMany(RecruitmentStageResult::class, 'application_id');
    }

    public function offerLetters()
    {
        return $this->hasMany(OfferLetter::class, 'application_id');
    }
}
