<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_settings', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id')->index();
            $table->string('company_name')->index();
            $table->integer('year')->index();
            $table->string('leave_type'); // Sick, Paid, Casual
            $table->decimal('monthly_allocation', 10, 2)->default(0);
            $table->decimal('yearly_allocation', 10, 2)->default(0);
            $table->decimal('carry_forward_limit', 10, 2)->default(0);
            $table->decimal('encashment_limit', 10, 2)->default(0);
            $table->timestamps();

            // Unique key: one record per corp+company+year+leaveType
            $table->unique(['corp_id', 'company_name', 'year', 'leave_type'], 'unique_leave_setting');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_settings');
    }
};
