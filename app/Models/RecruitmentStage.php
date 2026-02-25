<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentStage extends Model
{
    use HasFactory;

    protected $table = 'recruitment_stages';

    protected $fillable = [
        'corp_id', 'stage_name', 'stage_order', 'stage_type', 'description', 'is_active',
    ];
}
