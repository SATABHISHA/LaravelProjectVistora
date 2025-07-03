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
        Schema::create('employee_insurance_policies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id');
            $table->string('empcode');
            $table->string('name');
            $table->string('relationship');
            $table->string('dob');
            $table->string('gender');
            $table->string('policy_no');
            $table->string('insurance_type');
            $table->string('assured_sum');
            $table->string('premium');
            $table->string('issue_date');
            $table->string('valid_upto');
            $table->string('color', 7); // Add this line before $table->timestamps()
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_insurance_policies');
    }
};
