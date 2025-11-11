<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsFeedReview extends Model
{
    use HasFactory;

    protected $table = 'news_feed_reviews';

    protected $fillable = [
        'corpId',
        'puid',
        'EmpCode',
        'companyName',
        'employeeFullName',
        'isLiked',
        'comment',
        'date',
        'time',
    ];

    /**
     * Get the news feed that this review belongs to
     */
    public function newsFeed()
    {
        return $this->belongsTo(NewsFeed::class, 'puid', 'puid');
    }
}
