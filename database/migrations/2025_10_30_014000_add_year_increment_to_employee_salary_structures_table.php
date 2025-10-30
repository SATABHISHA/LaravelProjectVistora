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
        Schema::table('employee_salary_structures', function (Blueprint $table) {
            $table->string('year')->nullable()->after('updated_at');
            $table->string('increment')->nullable()->after('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_salary_structures', function (Blueprint $table) {
            $table->dropColumn(['year', 'increment']);
        });
    }
};
