<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Esi extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'esi';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'corpId',
        'companyName',
        'state',
        'incomeRange',
        'esiAmount',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'incomeRange' => '0',
        'esiAmount' => '0',
    ];
}
