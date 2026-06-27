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
            $table->index(
                ['corpId', 'companyName', 'year', 'month'],
                'attendance_summary_filter_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_attendance_summary', function (Blueprint $table) {
            $table->dropIndex('attendance_summary_filter_idx');
        });
    }
};
