<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialProfileAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id',
        'profile_menu_name',
        'social_profile_visible_yn',
        'hide_yn'
    ];
}
