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
        Schema::create('employee_attendance_summary', function (Blueprint $table) {
            $table->id();
            $table->string('corpId', 10);
            $table->string('empCode', 20);
            $table->string('companyName', 100);
            $table->integer('totalPresent')->default(0);
            $table->integer('workingDays')->default(0);
            $table->integer('holidays')->default(0);
            $table->integer('weekOff')->default(0);
            $table->double('leave')->default(0);
            $table->string('month', 30);
            $table->string('year', 4);
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['corpId', 'empCode']);
            $table->index(['corpId', 'companyName']);
            $table->index(['year', 'month']);
            $table->index(['corpId', 'year', 'month']);
            
            // Add unique constraint to prevent duplicate records
            $table->unique(['corpId', 'empCode', 'companyName', 'year', 'month'], 'attendance_summary_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_summary');
    }
};
