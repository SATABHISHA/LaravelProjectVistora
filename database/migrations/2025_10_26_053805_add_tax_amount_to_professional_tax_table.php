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
        Schema::table('professional_tax', function (Blueprint $table) {
            $table->string('taxAmount')->nullable()->after('aboveIncome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professional_tax', function (Blueprint $table) {
            $table->dropColumn('taxAmount');
        });
    }
};
