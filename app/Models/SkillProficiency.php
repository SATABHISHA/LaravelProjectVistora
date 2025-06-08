<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkillProficiency extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id',
        'skillproficiency_name',
    ];
}
