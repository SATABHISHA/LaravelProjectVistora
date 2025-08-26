<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormulaBuilder extends Model
{
    use HasFactory;

    protected $fillable = [
        'corpId',
        'puid',
        'paygroupPuid',
        'componentGroupName',
        'componentName',
        'componentNameRefersTo',
        'referenceValue', // Added the new field
        'formula'
    ];
}
