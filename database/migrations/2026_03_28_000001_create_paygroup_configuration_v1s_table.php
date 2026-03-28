<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paygroup_configuration_v1s', function (Blueprint $table) {
            $table->id();
            $table->string('corpId');
            $table->string('puid')->unique();
            $table->string('GroupName');
            $table->text('IncludedComponents')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paygroup_configuration_v1s');
    }
};
