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
        // Country Table
        Schema::create('countries', function (Blueprint $table) {
            $table->bigIncrements('country_id');
            $table->string('country_name')->unique();
            $table->timestamps();
        });

        // State Table
        Schema::create('states', function (Blueprint $table) {
            $table->id('state_id');
            $table->string('state_name');
            $table->timestamps();
        });

        // City Table
        Schema::create('cities', function (Blueprint $table) {
            $table->id('city_id');
            $table->string('city_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
