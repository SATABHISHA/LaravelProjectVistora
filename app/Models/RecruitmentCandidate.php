<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentCandidate extends Model
{
    use HasFactory;

    protected $table = 'recruitment_candidates';

    protected $fillable = [
        'corp_id', 'first_name', 'last_name', 'email', 'phone', 'dob', 'gender',
        'current_location', 'highest_qualification', 'total_experience_years',
        'current_ctc', 'expected_ctc', 'notice_period', 'resume_path',
        'linkedin_url', 'source', 'referred_by', 'skills', 'status',
    ];

    public function applications()
    {
        return $this->hasMany(RecruitmentApplication::class, 'candidate_id');
    }
}
