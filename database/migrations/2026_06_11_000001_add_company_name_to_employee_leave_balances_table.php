<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('emp_full_name');

            // Composite index for common query patterns
            $table->index(['corp_id', 'company_name', 'year', 'emp_code'], 'idx_elb_corp_company_year_emp');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->dropIndex('idx_elb_corp_company_year_emp');
            $table->dropColumn('company_name');
        });
    }
};
