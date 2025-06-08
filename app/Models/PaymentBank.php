<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentBank extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id',
        'bank_name',
        'alias_name',
        'account_no',
        'challan_report_format',
        'challan_report',
        'transaction_type',
        'branch_name',
        'bsr_code',
        'ifsc_code',
        'micr_code',
        'iban_no',
        'location',
        'address',
        'activeyn',
    ];
}
