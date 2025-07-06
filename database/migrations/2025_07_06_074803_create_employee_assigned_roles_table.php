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
        Schema::create('employee_assigned_roles', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('role_name');
            $table->string('employee_name');
            $table->string('empcode')->nullable();
            $table->string('company_names');
            $table->string('business_unit');
            $table->string('department');
            $table->string('sub_department_names');
            $table->string('designation');
            $table->string('grade');
            $table->string('level');
            $table->string('region');
            $table->string('branch');
            $table->string('sub_branch');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_assigned_roles');
    }
};
