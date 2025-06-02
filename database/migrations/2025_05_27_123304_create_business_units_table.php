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
        Schema::create('business_units', function (Blueprint $table) {
            $table->bigIncrements('business_unit_id');
            $table->string('corp_id');
            $table->string('business_unit_name');
            $table->boolean('active_yn')->default(true);
            $table->timestamps();

            // $table->foreign('corp_id')->references('corp_id')->on('company_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_units');
    }
};
