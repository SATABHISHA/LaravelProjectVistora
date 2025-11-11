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
        // Add EmpCode to news_feed table
        Schema::table('news_feed', function (Blueprint $table) {
            $table->string('EmpCode', 20)->after('puid')->index();
        });

        // Add EmpCode to news_feed_reviews table
        Schema::table('news_feed_reviews', function (Blueprint $table) {
            $table->string('EmpCode', 20)->after('puid')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_feed', function (Blueprint $table) {
            $table->dropColumn('EmpCode');
        });

        Schema::table('news_feed_reviews', function (Blueprint $table) {
            $table->dropColumn('EmpCode');
        });
    }
};
