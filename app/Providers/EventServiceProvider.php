<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\TaskCompleted::class => [
            \App\Listeners\UpdateUserStats::class,
            \App\Listeners\NotifyTaskCompleted::class,
        ],
        \App\Events\ProjectCreated::class => [
            \App\Listeners\UpdateUserProjectsCount::class,
        ],
        \App\Events\UserJoinedProject::class => [
            \App\Listeners\UpdateUserProjectsCountOnJoin::class,
        ],
        \App\Events\ManagerTaskCompleted::class => [
            \App\Listeners\UpdateManagerTaskStatus::class,
            \App\Listeners\NotifyManagerTaskCompleted::class,
        ],
        \App\Events\TaskNotificationEvent::class => [
            \App\Listeners\NotifyTaskParticipants::class,
        ],
        \App\Events\ProjectNotificationEvent::class => [
            \App\Listeners\NotifyProjectMembers::class,
        ],
        \App\Events\InvitationNotificationEvent::class => [
            \App\Listeners\NotifyInvitationParticipants::class,
        ],
        \App\Events\NotificationSent::class => [
            \App\Listeners\SendEmailNotification::class,
            \App\Listeners\SendFcmPushNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
