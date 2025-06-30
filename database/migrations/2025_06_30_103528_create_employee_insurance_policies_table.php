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
            $table->date('dob');
            $table->string('gender');
            $table->string('policy_no');
            $table->string('insurance_type');
            $table->decimal('assured_sum', 15, 2);
            $table->decimal('premium', 15, 2);
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
