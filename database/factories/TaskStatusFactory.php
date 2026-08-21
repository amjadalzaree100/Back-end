<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStatus>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter instead.
 */
class TaskStatusFactory extends Factory
{
    protected $model = TaskStatus::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $names = ['To Do', 'In Progress', 'Done', 'Review', 'Testing'];

        return [
            'project_id' => Project::factory(),
            'name' => $names[($i - 1) % count($names)].($i > count($names) ? ' '.$i : ''),
            'position' => $i,
        ];
    }
}
