<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomCity extends Model
{
    use HasFactory;

    protected $fillable = ['corp_id', 'city_name'];
}
