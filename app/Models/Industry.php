<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    use HasFactory;

    protected $primaryKey = 'industry_id';
    protected $fillable = ['corp_id','industry_name'];
}
