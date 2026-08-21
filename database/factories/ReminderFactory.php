<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reminder>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $titles = [
            'Task deadline approaching',
            'Meeting reminder',
            'Follow up on task',
            'Project update needed',
            'Review pending changes',
            'Team standup tomorrow',
        ];

        $messages = [
            'Your task is due soon. Make sure to wrap up the remaining work.',
            'Do not forget about the scheduled meeting this afternoon.',
            'Follow up with the team on the status of the assigned task.',
            'The project needs a status update from your side.',
            'Please review the pending changes before the end of the day.',
            'Join the standup tomorrow morning to share your progress.',
        ];

        $statuses = ['pending', 'sent'];

        return [
            'user_id' => User::factory(),
            'title' => $titles[($i - 1) % count($titles)].' '.$i,
            'message' => $messages[($i - 1) % count($messages)],
            'remind_at' => now()->addDays(rand(1, 30))->addMinutes(rand(0, 59)),
            'status' => $statuses[($i - 1) % count($statuses)],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'sent']);
    }
}