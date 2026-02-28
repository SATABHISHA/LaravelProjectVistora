<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ts_users', function (Blueprint $table) {
            $table->string('corp_id')->nullable()->after('id');
            $table->unsignedBigInteger('vistora_user_login_id')->nullable()->after('corp_id');

            $table->index('corp_id');
            $table->index('vistora_user_login_id');
        });
    }

    public function down(): void
    {
        Schema::table('ts_users', function (Blueprint $table) {
            $table->dropIndex(['corp_id']);
            $table->dropIndex(['vistora_user_login_id']);
            $table->dropColumn(['corp_id', 'vistora_user_login_id']);
        });
    }
};
