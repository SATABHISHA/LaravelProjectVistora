<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsFeed extends Model
{
    use HasFactory;

    protected $table = 'news_feed';

    protected $fillable = [
        'puid',
        'EmpCode',
        'corpId',
        'companyName',
        'employeeFullName',
        'body',
        'date',
        'time',
    ];

    /**
     * Boot function to auto-generate puid
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($newsFeed) {
            if (empty($newsFeed->puid)) {
                $newsFeed->puid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get all reviews for this news feed entry
     */
    public function reviews()
    {
        return $this->hasMany(NewsFeedReview::class, 'puid', 'puid');
    }

    /**
     * Get all likes for this news feed entry
     */
    public function likes()
    {
        return $this->hasMany(NewsFeedLike::class, 'puid', 'puid');
    }

    /**
     * Get count of likes
     */
    public function likesCount()
    {
        return $this->reviews()->where('isLiked', '1')->count();
    }
}
