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
        Schema::create('children', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id');
            $table->string('EmpCode');
            $table->string('ChildName');
            $table->string('ChildDob');
            $table->string('ChildGender');
            $table->integer('DependentYN')->default(0);
            $table->integer('GoingSchoolYN')->default(0);
            $table->integer('StayingHostel')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
