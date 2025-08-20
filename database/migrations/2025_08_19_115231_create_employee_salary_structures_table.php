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
        Schema::create('employee_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->string('corpId', 10);
            $table->string('puid', 50); // Added puid field
            $table->string('empCode', 20);
            $table->string('companyName', 100); // Increased length from 50 to 100
            $table->string('salaryRevisionMonth', 11);
            $table->string('arrearWithEffectFrom', 11);
            $table->string('payGroup', 50); // Increased length from 20 to 50
            $table->string('ctc', 50); // Increased length from 20 to 50
            $table->string('ctcYearly', 50); // Increased length from 20 to 50
            $table->string('monthlyBasic', 50); // Increased length from 20 to 50
            $table->string('leaveEnchashOnGross', 50); // Increased length from 20 to 50
            $table->string('performanceBonus', 50)->nullable(); // Increased length from 20 to 50
            $table->text('grossList'); // Changed from varchar(255) to TEXT
            $table->string('otherAlowances', 50)->nullable(); // Increased length from 20 to 50
            $table->text('otherBenifits')->nullable(); // Changed from varchar(255) to TEXT
            $table->text('recurringDeductions')->nullable(); // Changed from varchar(255) to TEXT
            $table->string('aplb', 50)->nullable(); // Increased length from 20 to 50
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salary_structures');
    }
};
