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
        Schema::create('employee_payroll_salary_process', function (Blueprint $table) {
            $table->id();
            $table->string('corpId', 10);
            $table->string('empCode', 20);
            $table->string('companyName', 100);
            $table->string('year', 4);
            $table->string('month', 2);
            $table->text('grossList');
            $table->text('otherAllowances')->nullable();
            $table->text('otherBenefits')->nullable();
            $table->text('recurringDeduction')->nullable();
            $table->string('status');
            $table->integer('isShownToEmployeeYn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_salary_process');
    }
};
