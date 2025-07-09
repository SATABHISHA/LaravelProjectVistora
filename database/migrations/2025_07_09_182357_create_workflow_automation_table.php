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
        Schema::create('workflow_automation', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->integer('workflow_recruitment_yn');
            $table->integer('workflow_workforce_yn');
            $table->integer('workflow_officetime_yn');
            $table->integer('workflow_payroll_yn');
            $table->integer('workflow_expense_yn');
            $table->integer('workflow_performance_yn');
            $table->integer('workflow_asset_yn');
            $table->string('workflow_name');
            $table->string('description')->nullable();
            $table->string('request_type');
            $table->string('flow_type');
            $table->string('applicability');
            $table->string('advance_applicability');
            $table->string('from_days');
            $table->string('to_days');
            $table->integer('conditional_workflowYN');
            $table->integer('activeYN'); // <-- Add this line
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_automation');
    }
};
