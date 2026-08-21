<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign('groups_manager_id_foreign');
            $table->foreignId('manager_id')->nullable()->change();
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign('groups_created_by_foreign');
            $table->foreignId('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->dropForeign('group_members_added_by_foreign');
            $table->foreignId('added_by')->nullable()->change();
            $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign('comments_user_id_foreign');
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('task_status_histories', function (Blueprint $table) {
            $table->dropForeign('task_status_histories_changed_by_foreign');
            $table->foreignId('changed_by')->nullable()->change();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('task_assignment_histories', function (Blueprint $table) {
            $table->dropForeign('task_assignment_histories_assigned_by_foreign');
            $table->foreignId('assigned_by')->nullable()->change();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('task_assignment_histories', function (Blueprint $table) {
            $table->dropForeign('task_assignment_histories_user_id_foreign');
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('chains', function (Blueprint $table) {
            $table->dropForeign('chains_created_by_foreign');
            $table->foreignId('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chains', function (Blueprint $table) {
            $table->dropForeign('chains_created_by_foreign');
            $table->foreignId('created_by')->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('task_assignment_histories', function (Blueprint $table) {
            $table->dropForeign('task_assignment_histories_user_id_foreign');
            $table->foreignId('user_id')->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('task_assignment_histories', function (Blueprint $table) {
            $table->dropForeign('task_assignment_histories_assigned_by_foreign');
            $table->foreignId('assigned_by')->change();
            $table->foreign('assigned_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('task_status_histories', function (Blueprint $table) {
            $table->dropForeign('task_status_histories_changed_by_foreign');
            $table->foreignId('changed_by')->change();
            $table->foreign('changed_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign('comments_user_id_foreign');
            $table->foreignId('user_id')->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->dropForeign('group_members_added_by_foreign');
            $table->foreignId('added_by')->change();
            $table->foreign('added_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign('groups_created_by_foreign');
            $table->foreignId('created_by')->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign('groups_manager_id_foreign');
            $table->foreignId('manager_id')->nullable()->change();
            $table->foreign('manager_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};