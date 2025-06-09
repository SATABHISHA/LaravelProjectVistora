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
        Schema::create('vendors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('activeYn')->default(1);
            $table->string('corp_id');
            $table->string('vendor_code');
            $table->string('company_name');
            $table->string('vendor_address');
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->string('pin');
            $table->string('gstin');
            $table->string('primary_contact_name');
            $table->string('primary_mobile_no');
            $table->string('primary_phone');
            $table->string('primary_email_id');
            $table->string('primary_contact_for');
            $table->string('secondary_contact_name')->nullable();
            $table->string('secondary_mobile_no')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('secondary_email_id')->nullable();
            $table->string('secondary_contact_for')->nullable();
            $table->string('vendor_field1')->nullable();
            $table->string('vendor_field2')->nullable();
            $table->string('vendor_field3')->nullable();
            $table->string('vendor_field4')->nullable();
            $table->string('vendor_field5')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
