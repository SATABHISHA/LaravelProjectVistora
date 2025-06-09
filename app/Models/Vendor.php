<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'activeYn', 'corp_id', 'vendor_code', 'company_name', 'vendor_address', 'country', 'state', 'city', 'pin', 'gstin',
        'primary_contact_name', 'primary_mobile_no', 'primary_phone', 'primary_email_id', 'primary_contact_for',
        'secondary_contact_name', 'secondary_mobile_no', 'secondary_phone', 'secondary_email_id', 'secondary_contact_for',
        'vendor_field1', 'vendor_field2', 'vendor_field3', 'vendor_field4', 'vendor_field5'
    ];
}
