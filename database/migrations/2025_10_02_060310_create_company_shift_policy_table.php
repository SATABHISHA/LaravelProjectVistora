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
        Schema::create('company_shift_policy', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('company_name');
            $table->string('shift_code');
            $table->timestamps();

            // Add unique constraint to prevent duplicate combinations
            $table->unique(['corp_id', 'company_name', 'shift_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_shift_policy');
    }
};
