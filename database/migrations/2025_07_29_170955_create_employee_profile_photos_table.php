<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeProfilePhotosTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_profile_photos', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code');
            $table->string('corp_id');
            $table->string('photo_url'); // store file path or URL
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_profile_photos');
    }
};
