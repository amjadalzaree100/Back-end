<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaskAssignmentHistory>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class TaskAssignmentHistoryFactory extends Factory
{
    protected $model = TaskAssignmentHistory::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $actions = ['assigned', 'unassigned'];

        return [
            'task_id' => Task::factory(),
            'user_id' => rand(0, 1) ? User::factory() : null,
            'assigned_by' => rand(0, 1) ? User::factory() : null,
            'action' => $actions[($i - 1) % count($actions)],
            'assigned_at' => now()->subDays(rand(0, 30))->subMinutes(rand(0, 59)),
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn () => ['action' => 'assigned']);
    }

    public function unassigned(): static
    {
        return $this->state(fn () => ['action' => 'unassigned']);
    }
}