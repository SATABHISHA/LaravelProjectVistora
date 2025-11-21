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
        Schema::create('fms_employee_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corpId', 10)->index();
            $table->string('companyName', 100);
            $table->string('empCode', 20);
            $table->string('fileCategory', 50);
            $table->string('filename', 255);
            $table->string('file', 500); // File path
            $table->unsignedBigInteger('file_size')->default(0); // Size in bytes
            $table->timestamps();

            // Composite indexes for faster lookups
            $table->index(['corpId', 'companyName']);
            $table->index(['corpId', 'companyName', 'fileCategory']);
            $table->index(['corpId', 'empCode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fms_employee_documents');
    }
};
