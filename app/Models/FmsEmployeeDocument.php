<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FmsEmployeeDocument extends Model
{
    use HasFactory;

    protected $table = 'fms_employee_documents';

    protected $fillable = [
        'corpId',
        'companyName',
        'empCode',
        'fileCategory',
        'filename',
        'file',
        'file_size',
    ];
}
