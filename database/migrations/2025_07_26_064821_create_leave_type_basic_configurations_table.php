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
        Schema::create('leave_type_basic_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('puid');
            $table->string('corpid');
            $table->string('leaveCode');
            $table->string('leaveName');
            $table->string('leaveCycleStartMonth');
            $table->string('leaveCycleEndMonth');
            $table->string('leaveTypeTobeCredited');
            $table->string('LimitDays');
            $table->string('LeaveType');
            $table->integer('encahsmentAllowedYN');
            $table->integer('isConfigurationCompletedYN');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_type_basic_configurations');
    }
};
