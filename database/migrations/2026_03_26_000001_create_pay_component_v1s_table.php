<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_component_v1s', function (Blueprint $table) {
            $table->id();
            $table->string('corpId');
            $table->string('puid')->unique();
            $table->string('componentName');
            $table->string('companyName');
            $table->string('payType');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_component_v1s');
    }
};
