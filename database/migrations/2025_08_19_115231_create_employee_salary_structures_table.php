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
            $table->string('companyName', 50);
            $table->string('salaryRevisionMonth', 11);
            $table->string('arrearWithEffectFrom', 11);
            $table->string('payGroup', 20);
            $table->string('ctc', 20);
            $table->string('ctcYearly', 20);
            $table->string('monthlyBasic', 20);
            $table->string('leaveEnchashOnGross', 20);
            $table->string('performanceBonus', 20)->nullable();
            $table->string('grossList', 255);
            $table->string('otherAlowances', 20)->nullable();
            $table->string('otherBenifits', 255)->nullable();
            $table->string('recurringDeductions', 255)->nullable();
            $table->string('aplb', 20)->nullable();
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
