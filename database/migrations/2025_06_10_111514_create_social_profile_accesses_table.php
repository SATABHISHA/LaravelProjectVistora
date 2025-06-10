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
        Schema::create('social_profile_accesses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id');
            $table->string('profile_menu_name');
            $table->integer('social_profile_visible_yn')->default(0);
            $table->integer('hide_yn')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_profile_accesses');
    }
};
