<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corp_company_tags', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id')->index();
            $table->string('company_tag')->index();
            $table->tinyInteger('active_yn')->default(1);
            $table->timestamps();

            $table->unique(['corp_id', 'company_tag'], 'uniq_corp_company_tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corp_company_tags');
    }
};
