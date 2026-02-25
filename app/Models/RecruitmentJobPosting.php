<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentJobPosting extends Model
{
    use HasFactory;

    protected $table = 'recruitment_job_postings';

    protected $fillable = [
        'corp_id', 'job_title', 'department', 'sub_department', 'designation',
        'location', 'employment_type', 'no_of_openings', 'job_description',
        'requirements', 'min_salary', 'max_salary', 'currency',
        'application_deadline', 'status', 'created_by',
    ];

    public function applications()
    {
        return $this->hasMany(RecruitmentApplication::class, 'job_posting_id');
    }
}
