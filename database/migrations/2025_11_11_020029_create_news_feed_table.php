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
        Schema::create('news_feed', function (Blueprint $table) {
            $table->id();
            $table->string('puid', 100)->unique()->comment('Unique identifier for news feed entry');
            $table->string('corpId', 10)->index();
            $table->string('companyName', 100)->index();
            $table->string('employeeFullName', 150);
            $table->text('body');
            $table->string('date', 20);
            $table->string('time', 20);
            $table->timestamps();
            
            // Add index for faster filtering
            $table->index(['corpId', 'companyName']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_feed');
    }
};
