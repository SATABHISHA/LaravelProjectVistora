<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfirmationStatus extends Model
{
    protected $fillable = ['corp_id', 'confirmation_status'];
    protected $table = 'confirmation_status';
}
