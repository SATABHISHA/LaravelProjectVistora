<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsFeedLike extends Model
{
    use HasFactory;

    protected $table = 'news_feed_likes';

    protected $fillable = [
        'corpId',
        'puid',
        'EmpCode',
        'companyName',
        'employeeFullName',
        'date',
        'time',
    ];

    /**
     * Get the news feed that owns this like
     */
    public function newsFeed()
    {
        return $this->belongsTo(NewsFeed::class, 'puid', 'puid');
    }
}
