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
        Schema::create('professional_tax', function (Blueprint $table) {
            $table->id();
            $table->string('corpId');
            $table->string('companyName');
            $table->string('state');
            $table->string('minIncome')->nullable()->default('0');
            $table->string('maxIncome')->nullable()->default('0');
            $table->string('aboveIncome')->nullable()->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_tax');
    }
};
