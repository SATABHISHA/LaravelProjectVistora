<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyDetails extends Model
{
    use HasFactory;

    protected $table = 'company_details';
    protected $primaryKey = 'company_id';

    protected $fillable = [
        'corp_id', 'company_name', 'company_logo', 'registered_address', 'pin', 'country', 'state', 'city',
        'phone', 'fax', 'currency', 'contact_person', 'industry', 'signatory_name', 'gstin',
        'fcbk_url', 'youtube_url', 'twiter_url', 'insta_url', 'active_yn'
    ];
}
