<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use App\Events\TaskNotificationEvent;

class NotifyTaskCompleted
{
    public function handle(TaskCompleted $event): void
    {
        $task = $event->task;
        $project = $task->project;

        // Notify project owner + task creator about completion
        $userIds = array_unique([
            $project->created_by,
            $task->created_by,
        ]);

        TaskNotificationEvent::dispatch(
            userIds: $userIds,
            scenario: 'completed',
            task: $task,
        );
    }
}
