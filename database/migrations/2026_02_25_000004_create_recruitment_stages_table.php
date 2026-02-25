<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_stages', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('stage_name');            // Screening, Technical Round 1, HR Round, etc.
            $table->integer('stage_order')->default(1);
            $table->string('stage_type')->nullable(); // Telephonic, Video, Face-to-Face, Assessment
            $table->text('description')->nullable();
            $table->integer('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_stages');
    }
};
