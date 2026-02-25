<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferLetter extends Model
{
    use HasFactory;

    protected $table = 'offer_letters';

    protected $fillable = [
        'corp_id', 'application_id', 'candidate_id', 'template_id',
        'offer_reference_no', 'candidate_name', 'designation', 'department',
        'location', 'date_of_joining', 'ctc_annual', 'salary_breakdown',
        'rendered_content', 'pdf_path', 'status', 'sent_at',
        'responded_at', 'generated_by',
    ];

    protected $casts = [
        'salary_breakdown' => 'array',
        'sent_at'          => 'datetime',
        'responded_at'     => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(RecruitmentApplication::class, 'application_id');
    }

    public function candidate()
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    public function template()
    {
        return $this->belongsTo(OfferLetterTemplate::class, 'template_id');
    }
}
