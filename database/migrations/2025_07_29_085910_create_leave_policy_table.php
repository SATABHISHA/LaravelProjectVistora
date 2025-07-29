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
        Schema::create('leave_policy', function (Blueprint $table) {
            $table->id();
            $table->string('corpid');
            $table->string('puid');
            $table->string('policyName');
            $table->text('description')->nullable();
            $table->string('leaveType');
            $table->string('applicabilityType');
            $table->string('applicabilityOn');
            $table->string('advanceApplicabilityType');
            $table->string('advanceApplicabilityOn');
            $table->string('fromDays'); // <-- Added
            $table->string('toDays');   // <-- Added
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_policy');
    }
};
