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
            $table->string('empcode')->nullable();
            $table->string('company_name')->nullable(); // Added company_name field
            $table->integer('active_yn')->default(1);   // Changed to integer with default 1
            $table->integer('admin_yn')->default(0);    // Changed to integer with default 0
            $table->integer('supervisor_yn')->default(0); // Changed to integer with default 0
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
