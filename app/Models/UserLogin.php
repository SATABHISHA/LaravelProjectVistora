<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLogin extends Model
{
    use HasFactory;

    protected $table = 'userlogin';
    protected $primaryKey = 'user_login_id';

    protected $fillable = [
        'corp_id', 'email_id', 'username', 'password',
        'empcode', // <-- Added this line
        'company_name','active_yn', 'admin_yn', 'supervisor_yn'
    ];

    protected $hidden = ['password'];
}
