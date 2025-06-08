<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobFunction extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id',
        'jobfunction_name',
    ];
}
