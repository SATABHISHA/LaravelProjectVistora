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
        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('emp_code');
            $table->string('emp_full_name');
            $table->string('leave_type_puid'); // Reference to leave_type_basic_configurations puid
            $table->string('leave_code');
            $table->string('leave_name');
            $table->decimal('total_allotted', 8, 2)->default(0); // Total leave allotted for the year
            $table->decimal('used', 8, 2)->default(0); // Leaves used
            $table->decimal('balance', 8, 2)->default(0); // Remaining balance
            $table->decimal('carry_forward', 8, 2)->default(0); // Carry forward from previous year
            $table->integer('year'); // Leave year (e.g., 2025)
            $table->integer('month')->nullable(); // For monthly credited leaves tracking
            $table->string('credit_type')->default('yearly'); // yearly, monthly
            $table->boolean('is_lapsed')->default(false); // Whether leave has lapsed
            $table->timestamp('last_credited_at')->nullable(); // Last time leave was credited
            $table->timestamps();

            // Unique constraint to prevent duplicate leave allotment for same employee, leave type, year
            $table->unique(['corp_id', 'emp_code', 'leave_type_puid', 'year'], 'unique_leave_balance');
            
            // Indexes for faster queries
            $table->index(['corp_id', 'year']);
            $table->index(['corp_id', 'emp_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_leave_balances');
    }
};
