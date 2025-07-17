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
        Schema::create('shiftpolicy', function (Blueprint $table) {
            $table->id();
            $table->string('puid');
            $table->string('corp_id');
            $table->string('shift_code');
            $table->string('shift_name');
            $table->string('shift_start_time');
            $table->string('first_half');
            $table->string('second_half');
            $table->string('checkin');
            $table->string('gracetime_early');
            $table->string('gracetime_late');
            $table->string('absence_halfday');
            $table->string('absence_fullday');
            $table->string('absence_halfday_absent_aftr');
            $table->string('absence_fullday_absent_aftr');
            $table->string('absence_secondhalf_absent_chckout_before');
            $table->integer('absence_shiftallowance_yn');
            $table->integer('absence_restrict_manager_backdate_yn');
            $table->integer('absence_restrict_hr_backdate_yn');
            $table->integer('absence_restrict_manager_future');
            $table->integer('absence_restrict_hr_future');
            $table->integer('adv_settings_sihft_break_deduction_yn');
            $table->integer('adv_settings_deduct_time_before_shift_yn');
            $table->integer('adv_settings_restrict_work_aftr_cutoff_yn');
            $table->integer('adv_settings_visible_in_wrkplan_rqst_yn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shiftpolicy');
    }
};
