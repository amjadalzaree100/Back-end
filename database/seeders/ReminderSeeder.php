<?php

namespace Database\Seeders;

use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReminderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        $templates = [
            ['Task deadline approaching', 'Complete the API integration by Friday'],
            ['Meeting reminder', 'Team standup in 30 minutes'],
            ['Follow up on task', 'Check progress on database migration'],
            ['Review pending', 'Code review needed for PR #123'],
            ['Weekly report', 'Submit your weekly progress report before noon'],
            ['Sprint planning', 'Sprint planning meeting has been moved to tomorrow'],
            ['Deployment window', 'Staging deploy is scheduled for this afternoon'],
            ['Code freeze', 'Feature freeze starts at the end of the week'],
        ];

        foreach ($users as $user) {
            $reminderCount = rand(3, 5);

            for ($i = 0; $i < $reminderCount; $i++) {
                // ~70% pending (future), ~30% sent (past).
                $status = rand(1, 100) <= 70 ? 'pending' : 'sent';

                $remindAt = $status === 'pending'
                    ? now()->addDays(rand(1, 30))->addHours(rand(0, 23))->addMinutes(rand(0, 59))
                    : now()->subDays(rand(1, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

                $template = $templates[array_rand($templates)];

                $reminder = Reminder::create([
                    'user_id' => $user->id,
                    'title' => $template[0],
                    'message' => $template[1],
                    'remind_at' => $remindAt,
                    'status' => $status,
                ]);

                // ~50% of reminders are linked to a task via the reminder_task pivot.
                if (rand(1, 100) <= 50) {
                    $taskQuery = Task::whereNotNull('due_date');

                    // Keep pending reminders in the future and before the task due date.
                    $task = $status === 'pending'
                        ? (clone $taskQuery)->where('due_date', '>', now()->addDays(2))->inRandomOrder()->first()
                        : (clone $taskQuery)->where('due_date', '<', now())->inRandomOrder()->first();

                    if ($task) {
                        $remindAt = (clone $task->due_date)->subDays(rand(1, 5))->addHours(rand(0, 23));

                        $reminder->update(['remind_at' => $remindAt]);
                        $reminder->tasks()->attach($task->id);
                    }
                }
            }
        }
    }
}