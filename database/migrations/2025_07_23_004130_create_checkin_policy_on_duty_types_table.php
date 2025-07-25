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
        Schema::create('checkin_policy_on_duty_types', function (Blueprint $table) {
            $table->id();
            $table->string('puid');
            $table->string('corp_id');
            $table->string('onduty_type');
            $table->string('onduty_applicability_type');
            $table->string('onduty_limit');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkin_policy_on_duty_types');
    }
};
