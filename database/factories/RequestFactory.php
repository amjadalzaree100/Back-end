<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Request>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class RequestFactory extends Factory
{
    protected $model = Request::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $messages = [
            "I'd like to join your project",
            "You're invited to collaborate",
            'I would love to contribute to this project',
            "Let's work together on this",
            'Please accept my request to join',
        ];

        $types = ['join_request', 'invitation'];
        $statuses = ['pending', 'approved', 'rejected'];

        $status = $statuses[($i - 1) % count($statuses)];
        $respondedAt = $status === 'pending' ? null : now()->subDays(rand(0, 7));
        $respondedBy = $status === 'pending' ? null : User::factory();

        return [
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'project_id' => Project::factory(),
            'type' => $types[($i - 1) % count($types)],
            'status' => $status,
            'message' => $messages[($i - 1) % count($messages)],
            'role' => rand(0, 1) ? 'member' : null,
            'responded_at' => $respondedAt,
            'responded_by' => $respondedBy,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'responded_at' => null,
            'responded_by' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'responded_at' => now()->subDays(rand(0, 7)),
            'responded_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'responded_at' => now()->subDays(rand(0, 7)),
            'responded_by' => User::factory(),
        ]);
    }

    public function joinRequest(): static
    {
        return $this->state(fn () => [
            'type' => 'join_request',
            'message' => "I'd like to join your project",
        ]);
    }

    public function invitation(): static
    {
        return $this->state(fn () => [
            'type' => 'invitation',
            'message' => "You're invited to collaborate",
        ]);
    }
}