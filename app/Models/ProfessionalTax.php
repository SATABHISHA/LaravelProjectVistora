<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalTax extends Model
{
    use HasFactory;

    protected $table = 'professional_tax';

    protected $fillable = [
        'corpId',
        'companyName',
        'state',
        'minIncome',
        'maxIncome',
        'aboveIncome',
    ];

    protected $attributes = [
        'minIncome' => '0',
        'maxIncome' => '0',
        'aboveIncome' => '0',
    ];
}
