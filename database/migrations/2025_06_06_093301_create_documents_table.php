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
        Schema::create('documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id');
            $table->string('name');
            $table->string('doc_type');
            $table->boolean('track_send_alert_yn')->default(false);
            $table->boolean('candidate_view_yn')->default(false);
            $table->boolean('candidate_edit_yn')->default(false);
            $table->boolean('emp_view_yn')->default(false);
            $table->boolean('emp_edit_yn')->default(false);
            $table->integer('mandatory_employee_yn')->default(0);
            $table->integer('mandatory_candidate_yn')->default(0);
            $table->integer('mandatory_to_convert_emp_yn')->default(0);
            $table->integer('mandatory_upcoming_join_yn')->default(0);
            $table->string('form_list')->nullable();
            $table->string('field_list')->nullable();
            $table->string('doc_upload')->nullable(); // File path
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
