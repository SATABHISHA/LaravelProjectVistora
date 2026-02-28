<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ts_project_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('ts_projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('ts_users')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('ts_users')->onDelete('cascade');
            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ts_project_assignments');
    }
};
