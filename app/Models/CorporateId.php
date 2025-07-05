<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateId extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id_name',
        'created_date',
        'active_yn',
        'one_time_payment_yn',
        'subscription_yn',
    ];
}
