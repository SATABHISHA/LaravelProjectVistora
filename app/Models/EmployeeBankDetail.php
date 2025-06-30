<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBankDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'empcode', 'SlryPayMode', 'SlryBankName', 'SlryBranchName', 'SlryIFSCCode', 'SlryAcntNo',
        'RimbPayMode', 'RimbBankName', 'RimbBranchName', 'RimbIFSCCode', 'RimbAcntNo'
    ];
}
