<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessUnit extends Model
{
    use HasFactory;

    protected $table = 'business_units';
    protected $primaryKey = 'business_unit_id';

    protected $fillable = [
        'company_name', 'business_unit_name', 'active_yn'
    ];
}
