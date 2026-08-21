<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $types = [
            'task_assigned' => ['Task assigned', 'A new task has been assigned to you.'],
            'comment_added' => ['New comment', 'Someone commented on your task.'],
            'project_invitation' => ['Project invitation', 'You have been invited to join a project.'],
            'request_approved' => ['Request approved', 'Your join request was approved.'],
            'request_rejected' => ['Request rejected', 'Your join request was rejected.'],
            'status_changed' => ['Status changed', 'The status of your task has been updated.'],
            'reminder_triggered' => ['Reminder', 'You have an upcoming reminder.'],
            'mention' => ['You were mentioned', 'Someone mentioned you in a comment.'],
        ];

        $keys = array_keys($types);
        $key = $keys[($i - 1) % count($keys)];

        $isRead = (bool) rand(0, 1);

        return [
            'user_id' => User::factory(),
            'type' => $key,
            'title' => $types[$key][0],
            'message' => $types[$key][1].' ('.$i.')',
            'data' => ['task_id' => rand(1, 100), 'actor_id' => rand(1, 100)],
            'is_read' => $isRead,
            'read_at' => $isRead ? now()->subHours(rand(0, 48)) : null,
            'action_url' => '/tasks/'.rand(1, 100),
            'icon' => ['bi-bell', 'bi-chat', 'bi-person-plus', 'bi-check-circle'][($i - 1) % 4],
        ];
    }

    public function unread(): static
    {
        return $this->state(fn () => [
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'is_read' => true,
            'read_at' => now()->subHours(rand(0, 48)),
        ]);
    }
}