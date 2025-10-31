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
        Schema::table('employee_attendance_summary', function (Blueprint $table) {
            $table->decimal('paidDays', 5, 1)->default(0)->after('leave');
            $table->integer('absent')->default(0)->after('paidDays');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_attendance_summary', function (Blueprint $table) {
            $table->dropColumn(['paidDays', 'absent']);
        });
    }
};
