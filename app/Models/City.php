<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;


    protected $primaryKey = 'city_id';
    protected $fillable = ['city_name', 'country_id', 'state_id', 'corp_id'];
}
