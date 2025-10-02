<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyShiftPolicy extends Model
{
    use HasFactory;

    protected $table = 'company_shift_policy';

    protected $fillable = [
        'corp_id',
        'company_name',
        'shift_code'
    ];

    /**
     * Relationship with ShiftPolicy table
     */
    public function shiftPolicy()
    {
        return $this->hasOne(ShiftPolicy::class, 'shift_code', 'shift_code');
    }

    /**
     * Get shift policies that match both corp_id and shift_code
     */
    public function shiftPolicyByCorpAndCode()
    {
        return $this->hasOne(ShiftPolicy::class, 'shift_code', 'shift_code')
                    ->where('corp_id', $this->corp_id);
    }
}
