<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorpCompanyTag extends Model
{
    use HasFactory;

    protected $table = 'corp_company_tags';

    protected $fillable = [
        'corp_id',
        'company_tag',
        'active_yn',
    ];

    protected $casts = [
        'active_yn' => 'integer',
    ];
}
