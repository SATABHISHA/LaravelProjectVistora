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
        Schema::create('news_feed_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('corpId', 10)->index();
            $table->string('puid', 100)->index()->comment('Foreign key to news_feed.puid');
            $table->string('companyName', 100)->index();
            $table->string('employeeFullName', 150);
            $table->string('isLiked', 1)->default('0')->comment('1 for liked, 0 for not liked');
            $table->text('comment')->nullable();
            $table->string('date', 20);
            $table->string('time', 20);
            $table->timestamps();
            
            // Add foreign key constraint
            $table->foreign('puid')->references('puid')->on('news_feed')->onDelete('cascade');
            
            // Add composite index for faster filtering
            $table->index(['puid', 'isLiked']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_feed_reviews');
    }
};
