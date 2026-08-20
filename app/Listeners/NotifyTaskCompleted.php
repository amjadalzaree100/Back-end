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

        // Check if this completed task unblocks dependent tasks
        foreach ($task->dependents as $dependent) {
            $dependentUserIds = [];
            if ($dependent->assigned_to) {
                $dependentUserIds[] = $dependent->assigned_to;
            }
            $dependentUserIds = array_merge(
                $dependentUserIds,
                $dependent->assigned_to ? [$dependent->assigned_to] : [],
            );
            $dependentUserIds = array_unique($dependentUserIds);

            if (!empty($dependentUserIds)) {
                TaskNotificationEvent::dispatch(
                    userIds: $dependentUserIds,
                    scenario: 'dependency_resolved',
                    task: $dependent,
                );
            }
        }
    }
}
