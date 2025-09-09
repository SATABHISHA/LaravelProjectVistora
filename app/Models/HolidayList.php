<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayList extends Model
{
    use HasFactory;

    protected $table = 'holiday_lists';

    protected $fillable = [
        'corpId',
        'puid',
        'companyNames',
        'country',
        'state',
        'city',
        'holidayName',
        'holidayDate',
        'year',
        'holidayType',
        'recurringType'
    ];

    protected $casts = [
        'holidayDate' => 'date:Y-m-d' // ✅ This will format date as Y-m-d only
    ];

    // Scope for filtering by location
    public function scopeByLocation($query, $country, $state = null, $city = null)
    {
        $query->where('country', $country);
        
        if ($state) {
            $query->where('state', $state);
        }
        
        if ($city) {
            $query->where('city', $city);
        }
        
        return $query;
    }

    // Scope for filtering by corp and company
    public function scopeByCompany($query, $corpId, $companyNames = null)
    {
        $query->where('corpId', $corpId);
        
        if ($companyNames) {
            $query->where('companyNames', $companyNames);
        }
        
        return $query;
    }

    // Scope for filtering by date range
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('holidayDate', [$startDate, $endDate]);
    }

    // Scope for filtering by year
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    // Scope for filtering by holiday type
    public function scopeByType($query, $type)
    {
        return $query->where('holidayType', $type);
    }

    // Scope for recurring holidays
    public function scopeRecurring($query, $type = null)
    {
        $query->where('recurringType', '!=', 'None');
        
        if ($type) {
            $query->where('recurringType', $type);
        }
        
        return $query;
    }
}
