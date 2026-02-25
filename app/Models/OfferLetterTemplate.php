<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferLetterTemplate extends Model
{
    use HasFactory;

    protected $table = 'offer_letter_templates';

    protected $fillable = [
        'corp_id', 'template_name', 'header_content', 'body_content', 'footer_content',
        'company_logo_path', 'digital_signature_path', 'signatory_name',
        'signatory_designation', 'salary_currency', 'salary_components',
        'salary_notes', 'is_active', 'created_by',
    ];

    protected $casts = [
        'salary_components' => 'array',
    ];
}
