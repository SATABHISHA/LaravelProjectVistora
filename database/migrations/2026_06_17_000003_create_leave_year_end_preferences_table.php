<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_year_end_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id')->index();
            $table->string('company_name')->index();
            $table->boolean('auto_allot_enabled')->default(false);
            $table->string('timezone')->default('Asia/Kolkata');
            $table->integer('last_run_year')->nullable();
            $table->timestamps();

            $table->unique(['corp_id', 'company_name'], 'uniq_leave_year_end_pref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_year_end_preferences');
    }
};
