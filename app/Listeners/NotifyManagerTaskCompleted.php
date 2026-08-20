<?php

namespace App\Listeners;

use App\Events\ManagerTaskCompleted;
use App\Events\TaskNotificationEvent;

class NotifyManagerTaskCompleted
{
    public function handle(ManagerTaskCompleted $event): void
    {
        $task = $event->task;

        if ($task->assignedGroup && $task->assignedGroup->manager_id) {
            TaskNotificationEvent::dispatch(
                userIds: [$task->assignedGroup->manager_id],
                scenario: 'completed',
                task: $task,
            );
        }
    }
}
