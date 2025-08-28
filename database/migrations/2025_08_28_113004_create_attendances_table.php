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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->string('corpId');
            $table->string('userName');
            $table->string('empCode');
            $table->string('companyName');
            $table->string('checkIn')->nullable();
            $table->string('checkOut')->nullable();
            $table->string('Lat')->nullable();
            $table->string('Long')->nullable();
            $table->string('Address')->nullable();
            $table->string('totalHrsForTheDay')->nullable();
            $table->string('status')->nullable(); // Added status field
            $table->string('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
