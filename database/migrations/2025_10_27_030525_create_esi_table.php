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
        Schema::create('esi', function (Blueprint $table) {
            $table->id();
            $table->string('corpId');
            $table->string('companyName');
            $table->string('state');
            $table->string('incomeRange')->nullable();
            $table->string('esiAmount')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esi');
    }
};
