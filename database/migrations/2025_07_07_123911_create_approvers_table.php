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
        Schema::create('approvers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id');
            $table->string('workflow_name');
            $table->string('request_type');
            $table->integer('workflow_recruitment_yn');
            $table->integer('workflow_workforce_yn');
            $table->integer('workflow_officetime_yn');
            $table->integer('workflow_payroll_yn');
            $table->integer('workflow_expense_yn');
            $table->integer('workflow_performance_yn');
            $table->integer('workflow_asset_yn');
            $table->string('approver');
            $table->integer('intimationYN');
            $table->string('due_day')->nullable();
            $table->string('turnaround_time')->nullable();
            $table->integer('active_yn')->default(1); // <-- Add this line
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvers');
    }
};
