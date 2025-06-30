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
        Schema::create('family_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id');
            $table->string('EmpCode');
            $table->string('FatherName')->nullable();
            $table->string('FatherDOB')->nullable();
            $table->string('MotherName')->nullable();
            $table->string('MotherDob')->nullable();
            $table->string('MaritalStatus')->nullable();
            $table->string('SpuseName')->nullable();
            $table->string('SpouseDob')->nullable();
            $table->string('MarriageDate')->nullable();
            $table->string('DependentName')->nullable();
            $table->string('DependentRelation')->nullable();
            $table->string('DependentDob')->nullable();
            $table->string('DependentGender')->nullable();
            $table->string('DependentRemarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_details');
    }
};
