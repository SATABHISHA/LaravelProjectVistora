<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'activeYn', 'corp_id', 'cust_name', 'cust_code', 'contact_name', 'email_id', 'phone',
        'cust_addr', 'country', 'state', 'city', 'pin'
    ];
}
