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
        Schema::create('company_storage', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('corpId', 10)->index();
            $table->unsignedBigInteger('size'); // raw numeric value
            $table->string('sizeUit', 5); // MB | GB | KB (spelling per request)
            $table->timestamps();
            $table->index(['corpId', 'sizeUit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_storage');
    }
};
