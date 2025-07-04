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
        Schema::create('employee_bank_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id');
            $table->string('empcode');
            $table->string('SlryPayMode')->nullable();
            $table->string('SlryBankName')->nullable();
            $table->string('SlryBranchName')->nullable();
            $table->string('SlryIFSCCode')->nullable();
            $table->string('SlryAcntNo')->nullable();
            $table->string('RimbPayMode')->nullable();
            $table->string('RimbBankName')->nullable();
            $table->string('RimbBranchName')->nullable();
            $table->string('RimbIFSCCode')->nullable();
            $table->string('RimbAcntNo')->nullable();
            $table->integer('same_as_salary_yn')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_bank_details');
    }
};
