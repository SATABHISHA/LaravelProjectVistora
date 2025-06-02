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
        Schema::create('subdepartments', function (Blueprint $table) {
            $table->id('sub_dept_id'); // Auto increment primary key
            $table->string('corp_id');
            $table->string('department_name');
            $table->string('sub_department_name');
            $table->integer('active_yn')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subdepartments');
    }
};
