<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaygroupConfigurationV1 extends Model
{
    use HasFactory;

    protected $table = 'paygroup_configuration_v1s';

    protected $fillable = [
        'corpId',
        'puid',
        'GroupName',
        'IncludedComponents',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaygroupConfigurationV1 $model) {
            if (empty($model->puid)) {
                $model->puid = 'PGV1-' . strtoupper(Str::random(8));
            }
        });
    }
}
