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
        Schema::create('holiday_lists', function (Blueprint $table) {
            $table->id();
            $table->string('corpId', 10)->index();
            $table->string('puid', 50)->unique();
            $table->string('companyNames', 255);
            $table->string('country', 50);
            $table->string('state', 50);
            $table->string('city', 50);
            $table->string('holidayName', 255);
            $table->date('holidayDate');
            $table->string('year', 4);
            $table->string('holidayType', 100); // Removed enum constraint
            $table->string('recurringType', 50); // Removed enum constraint
            $table->timestamps();
            
            // Add composite indexes for better query performance
            $table->index(['corpId', 'companyNames']);
            $table->index(['corpId', 'holidayDate']);
            $table->index(['country', 'state', 'city']);
            $table->index(['holidayType', 'recurringType']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_lists');
    }
};
