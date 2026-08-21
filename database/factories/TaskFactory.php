<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $titles = [
            'Design landing page',
            'Implement authentication',
            'Fix login bug',
            'Write API documentation',
            'Set up CI pipeline',
            'Review pull requests',
            'Optimize database queries',
            'Refactor legacy module',
            'Add unit tests',
            'Update dependencies',
        ];

        $descriptions = [
            'Create a polished and responsive landing page that showcases the product.',
            'Build a secure authentication flow with email verification and password reset.',
            'Investigate and resolve the reported login issue affecting a subset of users.',
            'Document all public API endpoints with request and response examples.',
            'Configure continuous integration to run tests and static analysis on every push.',
            'Review open pull requests, provide feedback, and merge approved changes.',
            'Analyze slow queries and apply indexes or caching to improve performance.',
            'Break the legacy module into smaller, maintainable components.',
            'Cover critical business logic with unit tests to prevent regressions.',
            'Upgrade framework and third-party packages to their latest stable versions.',
        ];

        $priority = ['urgent', 'high', 'medium', 'low'];

        $status = TaskStatus::inRandomOrder()->value('id')
            ?? TaskStatusFactory::new()->create()->id;

        return [
            'project_id' => Project::factory(),
            'title' => $titles[($i - 1) % count($titles)].' '.$i,
            'description' => $descriptions[($i - 1) % count($descriptions)],
            'status_id' => $status,
            'priority' => $priority[($i - 1) % count($priority)],
            'due_date' => now()->addDays(rand(1, 30)),
            'position' => $i,
            'created_by' => User::factory(),
            'assigned_to' => null,
            'completed_at' => null,
            'started_at' => null,
            'parent_task_id' => null,
            'allow_subtasks' => true,
            'can_be_assigned' => true,
            'assigned_group_id' => null,
            'is_archived' => false,
        ];
    }
}
