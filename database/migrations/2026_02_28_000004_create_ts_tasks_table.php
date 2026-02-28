<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ts_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'approved', 'rejected'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('ts_projects')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('ts_users')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('ts_users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('ts_users')->onDelete('set null');
            $table->index('status');
            $table->index('assigned_to');
            $table->index('assigned_by');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ts_tasks');
    }
};
