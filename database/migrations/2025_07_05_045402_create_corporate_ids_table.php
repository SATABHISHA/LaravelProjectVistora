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
        Schema::create('corporate_ids', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corp_id_name');
            $table->string('created_date');
            $table->integer('active_yn');
            $table->integer('one_time_payment_yn');
            $table->integer('subscription_yn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate_ids');
    }
};
