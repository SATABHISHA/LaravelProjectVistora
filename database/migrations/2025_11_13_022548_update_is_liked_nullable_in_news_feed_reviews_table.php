<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news_feed_reviews', function (Blueprint $table) {
            $table->string('isLiked', 1)->nullable()->default('0')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_feed_reviews', function (Blueprint $table) {
            $table->string('isLiked', 1)->nullable(false)->default('0')->change();
        });
    }
};
