<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveSetting extends Model
{
    use HasFactory;

    protected $table = 'leave_settings';

    protected $fillable = [
        'corp_id',
        'company_name',
        'year',
        'leave_type',
        'monthly_allocation',
        'yearly_allocation',
        'carry_forward_limit',
        'encashment_limit',
    ];

    protected $casts = [
        'year' => 'integer',
        'monthly_allocation' => 'decimal:2',
        'yearly_allocation' => 'decimal:2',
        'carry_forward_limit' => 'decimal:2',
        'encashment_limit' => 'decimal:2',
    ];
}
