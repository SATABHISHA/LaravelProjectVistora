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
        Schema::create('userlogin', function (Blueprint $table) {
            $table->id('user_login_id');
            $table->string('corp_id')->default('archer');
            $table->string('email_id')->unique();
            $table->string('username');
            $table->string('password');
            $table->string('empcode')->nullable(); // <-- Add this line
            $table->boolean('active_yn')->default(true);
            $table->boolean('admin_yn')->default(false);
            $table->boolean('supervisor_yn')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('userlogin');
    }
};
