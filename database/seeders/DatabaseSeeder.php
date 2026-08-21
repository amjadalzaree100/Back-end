<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProjectSeeder::class,
            TaskStatusSeeder::class,
            ProjectUserSeeder::class,
            TaskSeeder::class,

            // GroupSeeder (needs projects, users, project_users)
            GroupSeeder::class,

            // ChainSeeder (needs users)
            ChainSeeder::class,

            // ChainProjectSeeder (needs chains, projects, project_users)
            ChainProjectSeeder::class,

            // TaskStatusHistorySeeder (needs tasks, statuses)
            TaskStatusHistorySeeder::class,

            // TaskAssignmentHistorySeeder (needs tasks, users)
            TaskAssignmentHistorySeeder::class,

            // CommentSeeder (needs tasks, users)
            CommentSeeder::class,

            // ProjectCommentSeeder (needs projects, users)
            ProjectCommentSeeder::class,

            // ProjectReactionSeeder (needs projects, users)
            ProjectReactionSeeder::class,

            // RequestSeeder (needs projects, users)
            RequestSeeder::class,

            // ReminderSeeder (needs users, tasks)
            ReminderSeeder::class,

            // NotificationSeeder (needs users, tasks, projects)
            NotificationSeeder::class,

            // FavoriteSeeder (needs users)
            FavoriteSeeder::class,

            // FavoriteProjectSeeder (needs users, projects)
            FavoriteProjectSeeder::class,

            // RatingSeeder (needs projects, users)
            RatingSeeder::class,

            // ReportSeeder (needs users)
            ReportSeeder::class,

            // ProjectReportSeeder (needs users, projects)
            ProjectReportSeeder::class,

            // BlockedUserSeeder (needs users)
            BlockedUserSeeder::class,

            // FcmTokenSeeder (needs users)
            FcmTokenSeeder::class,

            // TaskTransferSeeder (needs tasks, projects, users)
            TaskTransferSeeder::class,

            // AdminSeeder (last, independent)
            AdminSeeder::class,
        ]);
    }
}