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
        Schema::create('payment_banks', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('corp_id');
            $table->string('alias_name')->nullable();
            $table->string('account_no')->nullable();
            $table->string('challan_report_format')->nullable();
            $table->string('challan_report')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('bsr_code')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('micr_code')->nullable();
            $table->string('iban_no')->nullable();
            $table->string('location')->nullable();
            $table->string('address')->nullable();
            $table->integer('activeyn')->default(1); // Added activeyn
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_banks');
    }
};
