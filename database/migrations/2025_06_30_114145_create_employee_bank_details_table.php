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
            $table->string('SlryPayMode');
            $table->string('SlryBankName');
            $table->string('SlryBranchName');
            $table->string('SlryIFSCCode');
            $table->string('SlryAcntNo');
            $table->string('RimbPayMode');
            $table->string('RimbBankName');
            $table->string('RimbBranchName');
            $table->string('RimbIFSCCode');
            $table->string('RimbAcntNo');
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
