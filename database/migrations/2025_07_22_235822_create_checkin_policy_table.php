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
        Schema::create('checkin_policy', function (Blueprint $table) {
            $table->id();
            $table->string('puid');
            $table->string('corp_id');
            $table->string('policy_name');
            $table->integer('web_checkin_yn');
            $table->integer('punches_yn');
            $table->string('punches_no');
            $table->integer('restrict_emp_marking_attndc_yn');
            $table->string('punch_start_time');
            $table->string('punch_end_time');
            $table->integer('IP_validation_yn');
            $table->string('from_ip');
            $table->string('to_ip');
            $table->integer('web_chckin_rqst_frm_whatsapp_yn');
            $table->integer('web_chckin_rqst_frm_teams_yn');
            $table->integer('mobile_chckin_yn');
            $table->integer('photo_attdnc_yn');
            $table->string('no_of_photos');
            $table->integer('location_attdnc_yn');
            $table->integer('adv_location_tracking_yn');
            $table->integer('specific_period_wrk_location_approval_yn');
            $table->string('location_approval_days_limit');
            $table->integer('max_punches_allowed_yn');
            $table->string('punches_no_allowed');
            $table->integer('restrict_emp_attndc_yn');
            $table->integer('attdnc_regularization_yn');
            $table->integer('emp_bck_dated_attdnc_regularization_yn');
            $table->string('emp_bck_dated_attdnc_regularization_days');
            $table->integer('mngr_bck_dated_regularization_yn');
            $table->string('mngr_bck_dated_regularization_days');
            $table->integer('hr_bck_dated_attdnc_regularization_yn');
            $table->string('hr_bck_dated_attdnc_regularization_days');
            $table->string('attdnc_regularization_limit_type');
            $table->string('attdnc_regularization_total_limit');
            $table->integer('ar_aftr_attdnc_process_yn');
            $table->integer('future_dtd_attdnc_regularization_yn');
            $table->integer('atleast_one_punch_attdnc_regularization_yn');
            $table->integer('for_ar_attachment_yn');
            $table->integer('whatsapp_ar_rqst_yn');
            $table->integer('teams_ar_rqst_yn');
            $table->integer('ar_week_off_emp_restricted_yn');
            $table->integer('ar_holidays_emp_restricted_yn');
            $table->integer('on_duty_yn');
            $table->integer('emp_bck_dtd_onduty_rqst_yn');
            $table->string('emp_bck_dtd_onduty_rqst_days');
            $table->integer('mngr_bck_dtd_onduty_rqst_yn');
            $table->string('mngr_bck_dtd_onduty_rqst_days');
            $table->integer('hr_bck_dtd_onduty_rqst_yn');
            $table->string('hr_bck_dtd_onduty_rqst_days');
            $table->integer('configure_overall_onduty_limit_yn');
            $table->integer('project_log_time_yn');
            $table->integer('raise_onduty_aftr_attdnc_process_yn');
            $table->integer('future_dtd_onduty_yn');
            $table->integer('restrict_manager_onduty_beyond_limit_yn');
            $table->integer('attchmnt_for_od_yn');
            $table->integer('whatsapp_od_rqst_yn');
            $table->integer('teams_od_rqst_yn');
            $table->integer('onduty_week_off_emp_restricted_yn');
            $table->integer('onduty_holidays_emp_restricted_yn');
            $table->string('applicability_type');
            $table->string('applicability_for');
            $table->string('advnc_applicability_type');
            $table->string('advnc_applicability_for');
            $table->string('from_days');
            $table->string('to_days');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkin_policy');
    }
};
