<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    use HasFactory;

    protected $table = 'requesttypes'; // <-- Add this line
    protected $fillable = ['corp_id', 'request_type_name'];
}
