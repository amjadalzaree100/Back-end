<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaskTransfer>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class TaskTransferFactory extends Factory
{
    protected $model = TaskTransfer::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $notes = [
            'Moved to keep the task in the correct project scope.',
            'Task was reassigned during project restructuring.',
            'Duplicate task merged into the target project.',
            'User requested the task be moved to the new project.',
            null,
        ];

        return [
            'task_id' => Task::factory(),
            'from_project_id' => Project::factory(),
            'to_project_id' => Project::factory(),
            'from_task_id' => rand(0, 1) ? Task::factory() : null,
            'to_task_id' => rand(0, 1) ? Task::factory() : null,
            'transferred_by' => User::factory(),
            'note' => $notes[($i - 1) % count($notes)],
            'transferred_at' => now()->subDays(rand(0, 30))->subMinutes(rand(0, 59)),
        ];
    }
}