<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TsTeamMember extends Model
{
    use HasFactory;

    protected $table = 'ts_team_members';

    protected $fillable = [
        'supervisor_id',
        'member_id',
        'corp_id',
    ];

    public function supervisor()
    {
        return $this->belongsTo(UserLogin::class, 'supervisor_id', 'user_login_id');
    }

    public function member()
    {
        return $this->belongsTo(UserLogin::class, 'member_id', 'user_login_id');
    }
}
