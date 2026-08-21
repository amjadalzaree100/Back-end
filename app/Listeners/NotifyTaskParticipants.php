<?php

namespace App\Listeners;

use App\Events\TaskNotificationEvent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTaskParticipants implements ShouldQueue
{
    public function handle(TaskNotificationEvent $event): void
    {
        $svc = app(NotificationService::class);

        [$title, $message, $icon, $url] = match ($event->scenario) {
            'assigned' => [
                'Task Assigned',
                $event->actor
                    ? "{$event->actor->name} assigned you to task: {$event->task->title}"
                    : "You have been assigned to task: {$event->task->title}",
                '📋',
                "/tasks/{$event->task->id}",
            ],
            'completed' => [
                'Task Completed',
                "Task \"{$event->task->title}\" has been completed",
                '✅',
                "/tasks/{$event->task->id}",
            ],
            'commented' => [
                'New Comment',
                $event->actor
                    ? "{$event->actor->name} commented on task: {$event->task->title}"
                    : "A comment was added to task: {$event->task->title}",
                '💬',
                "/tasks/{$event->task->id}",
            ],
            default => [
                'Task Update',
                "Update on task: {$event->task->title}",
                '📌',
                "/tasks/{$event->task->id}",
            ],
        };

        $svc->sendToMany($event->userIds, $title, $message, 'task', null, $url, $icon);
    }
}
