<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'puid',
        'corpId',
        'userName',
        'empCode',
        'companyName',
        'checkIn',
        'checkOut',
        'Lat',
        'Long',
        'Address',
        'totalHrsForTheDay',
        'status',
        'attendanceStatus',
        'date'
    ];
}
