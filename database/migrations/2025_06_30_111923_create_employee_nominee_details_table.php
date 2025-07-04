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
        Schema::create('employee_nominee_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id');
            $table->string('empcode');
            $table->string('statutory_type');
            $table->string('nominee_name'); // <-- Add this line
            $table->string('relation');
            $table->string('dob');
            $table->string('gender');
            $table->string('share_percent');
            $table->string('contact_no');
            $table->string('addr');
            $table->string('remarks')->nullable();
            $table->integer('minor_yn');
            $table->string('color', 7);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_nominee_details');
    }
};
