<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Drop FK constraints that reference ts_users from all timesheet tables
        Schema::table('ts_kpis', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('ts_daily_reports', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('ts_task_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('ts_tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['assigned_by']);
            $table->dropForeign(['approved_by']);
        });

        Schema::table('ts_project_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('ts_project_assignments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['assigned_by']);
        });

        Schema::table('ts_projects', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        // Step 2: Truncate all timesheet data (old ts_users IDs don't match userlogin IDs)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('ts_kpis')->truncate();
        DB::table('ts_daily_reports')->truncate();
        DB::table('ts_task_histories')->truncate();
        DB::table('ts_tasks')->truncate();
        DB::table('ts_project_histories')->truncate();
        DB::table('ts_project_assignments')->truncate();
        DB::table('ts_projects')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Step 3: Drop ts_users table (no longer needed - using userlogin instead)
        Schema::dropIfExists('ts_users');

        // Step 4: Create ts_team_members table for supervisor-subordinate mapping
        Schema::create('ts_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supervisor_id')->comment('References userlogin.user_login_id');
            $table->unsignedBigInteger('member_id')->comment('References userlogin.user_login_id');
            $table->string('corp_id');
            $table->timestamps();

            $table->unique(['supervisor_id', 'member_id'], 'ts_team_unique');
            $table->index('supervisor_id');
            $table->index('member_id');
            $table->index('corp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ts_team_members');

        // Recreate ts_users table
        Schema::create('ts_users', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id')->nullable();
            $table->unsignedBigInteger('vistora_user_login_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'supervisor', 'subordinate']);
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('supervisor_id')->references('id')->on('ts_users')->onDelete('set null');
            $table->index('role');
            $table->index('supervisor_id');
            $table->index('corp_id');
            $table->index('vistora_user_login_id');
        });

        // Note: FK constraints to ts_users on other tables would need to be re-added manually
    }
};
