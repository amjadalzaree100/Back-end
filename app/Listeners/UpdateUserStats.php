<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use App\Models\User;

class UpdateUserStats
{
    public function handle(TaskCompleted $event)
    {
        $task = $event->task;

        if ($task->assigned_to) {
            $user = User::find($task->assigned_to);
            if ($user && $user->profile) {
                $completedCount = $user->tasks()
                    ->whereNotNull('completed_at')
                    ->count();

                $user->profile->update(['tasks_completed' => $completedCount]);
            }
        }
    }
}
