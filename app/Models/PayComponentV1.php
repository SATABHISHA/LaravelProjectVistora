<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PayComponentV1 extends Model
{
    use HasFactory;

    protected $table = 'pay_component_v1s';

    protected $fillable = [
        'corpId',
        'puid',
        'componentName',
        'companyName',
        'payType',
    ];

    protected static function booted(): void
    {
        static::creating(function (PayComponentV1 $model) {
            if (empty($model->puid)) {
                $model->puid = 'PCV1-' . strtoupper(Str::random(8));
            }
        });
    }
}
