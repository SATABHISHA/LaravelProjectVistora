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
        Schema::create('formula_builders', function (Blueprint $table) {
            $table->id();
            $table->string('corpId');
            $table->string('puid');
            $table->string('paygroupPuid');
            $table->string('componentGroupName');
            $table->string('componentName');
            $table->string('componentNameRefersTo')->default('None'); // Added default value 'None'
            $table->string('referenceValue')->nullable(); // Added new referenceValue column as string
            $table->text('formula');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formula_builders');
    }
};
