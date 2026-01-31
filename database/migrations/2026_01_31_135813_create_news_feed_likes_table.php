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
        Schema::create('news_feed_likes', function (Blueprint $table) {
            $table->id();
            $table->string('corpId', 10)->index();
            $table->string('puid', 100)->index()->comment('Foreign key to news_feed.puid');
            $table->string('EmpCode', 20)->index();
            $table->string('companyName', 100)->index();
            $table->string('employeeFullName', 150);
            $table->string('date', 20);
            $table->string('time', 20);
            $table->timestamps();
            
            // Add foreign key constraint
            $table->foreign('puid')->references('puid')->on('news_feed')->onDelete('cascade');
            
            // Add unique constraint to prevent duplicate likes from same user
            $table->unique(['puid', 'corpId', 'EmpCode']);
            
            // Add composite index for faster filtering
            $table->index(['puid', 'EmpCode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_feed_likes');
    }
};
