<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomCountry extends Model
{
    use HasFactory;

    protected $fillable = ['corp_id', 'country_name'];
}
