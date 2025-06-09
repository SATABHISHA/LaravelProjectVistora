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
        Schema::create('customer_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('activeYn')->default(1);
            $table->string('corp_id');
            $table->string('cust_name');
            $table->string('cust_code');
            $table->string('contact_name');
            $table->string('email_id');
            $table->string('phone');
            $table->string('cust_addr');
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->string('pin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_details');
    }
};
