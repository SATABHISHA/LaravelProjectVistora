<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ts_project_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action'); // created, updated, timeline_extended, status_changed, member_added, member_removed
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('project_id')->references('id')->on('ts_projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('ts_users')->onDelete('cascade');
            $table->index('project_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ts_project_histories');
    }
};
