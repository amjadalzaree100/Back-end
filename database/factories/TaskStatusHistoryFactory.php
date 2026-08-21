<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaskStatusHistory>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class TaskStatusHistoryFactory extends Factory
{
    protected $model = TaskStatusHistory::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $fromStatusId = TaskStatus::inRandomOrder()->value('id');
        $toStatusId = TaskStatus::inRandomOrder()->value('id');

        return [
            'task_id' => Task::factory(),
            'from_status_id' => rand(0, 1) ? $fromStatusId : null,
            'to_status_id' => rand(0, 1) ? $toStatusId : null,
            'changed_by' => rand(0, 1) ? User::factory() : null,
            'changed_at' => now()->subDays(rand(0, 30))->subMinutes(rand(0, 59)),
        ];
    }
}