<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->dropUnique('unique_leave_balance');
            $table->unique(
                ['corp_id', 'emp_code', 'leave_type_puid', 'year', 'company_name'],
                'unique_leave_balance_company'
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->dropUnique('unique_leave_balance_company');
            $table->unique(['corp_id', 'emp_code', 'leave_type_puid', 'year'], 'unique_leave_balance');
        });
    }
};
