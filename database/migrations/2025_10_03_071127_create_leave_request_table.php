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
        Schema::create('leave_request', function (Blueprint $table) {
            $table->id();
            $table->string('puid');
            $table->string('corp_id');
            $table->string('company_name');
            $table->string('empcode');
            $table->string('full_name');
            $table->string('emp_designation');
            $table->string('from_date');
            $table->string('to_date');
            $table->text('reason')->nullable();
            $table->string('approved_reject_return_by')->nullable();
            $table->text('reject_reason')->nullable();
            $table->string('status');
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['corp_id', 'empcode']);
            $table->index('status');
            $table->index('puid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_request');
    }
};
