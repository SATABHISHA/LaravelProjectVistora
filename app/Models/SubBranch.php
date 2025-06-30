<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubBranch extends Model
{
    protected $fillable = ['corp_id', 'subbranch'];
    protected $table = 'subbranches'; // Add this line
}
