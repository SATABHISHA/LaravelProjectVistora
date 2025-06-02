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
        Schema::create('company_details', function (Blueprint $table) {
            $table->bigIncrements('company_id');
            $table->string('corp_id');
            $table->string('company_name');
            $table->string('company_logo')->nullable();
            $table->string('registered_address');
            $table->string('pin');
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->string('phone');
            $table->string('fax')->nullable();
            $table->string('currency');
            $table->string('contact_person');
            $table->string('industry');
            $table->string('signatory_name');
            $table->string('gstin');
            $table->string('fcbk_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('twiter_url')->nullable();
            $table->string('insta_url')->nullable();
            $table->boolean('active_yn')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};
