<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $alaa = User::where('email', 'alaa.gbh0@gmail.com')->first();
        $projects = Project::with('users')->get();

        $taskTitles = [
            'Setup project repository',
            'Write initial documentation',
            'Design database schema',
            'Create wireframes',
            'Implement authentication',
            'Build user dashboard',
            'Configure CI/CD pipeline',
            'Write unit tests',
            'Perform code review',
            'Deploy to staging',
            'Optimize queries',
            'Fix navigation bug',
            'Update dependencies',
            'Add error handling',
            'Implement search feature',
            'Create email templates',
            'Set up logging',
            'Conduct user testing',
            'Prepare release notes',
            'Monitor performance',
        ];

        $priorities = ['urgent', 'high', 'medium', 'low'];

        foreach ($projects as $project) {
            $statuses = TaskStatus::where('project_id', $project->id)->get();
            $members = $project->users->pluck('id')->toArray();

            if ($statuses->isEmpty() || empty($members)) {
                continue;
            }

            $taskCount = rand(4, 7);
            $usedTitles = [];

            for ($i = 0; $i < $taskCount; $i++) {
                $title = null;
                do {
                    $candidate = $taskTitles[array_rand($taskTitles)];
                    if (! in_array($candidate, $usedTitles)) {
                        $title = $candidate;
                        $usedTitles[] = $candidate;
                    }
                } while ($title === null);

                $status = $statuses->random();
                $assigneeId = $members[array_rand($members)];
                $priority = $priorities[array_rand($priorities)];

                $hasDueDate = rand(0, 3) !== 0; // 75% chance
                $dueDate = $hasDueDate ? now()->addDays(rand(-5, 14))->format('Y-m-d') : null;

                $completedAt = null;
                $startedAt = null;

                if ($status->name === 'Done') {
                    $completedAt = now()->subDays(rand(1, 10));
                    $startedAt = (clone $completedAt)->subDays(rand(1, 5));
                } elseif ($status->name === 'In Progress') {
                    $startedAt = now()->subDays(rand(1, 5));
                }

                $task = Task::create([
                    'project_id' => $project->id,
                    'title' => $title,
                    'description' => 'Detailed work for: ' . $title . ' in the ' . $project->name . ' project.',
                    'status_id' => $status->id,
                    'priority' => $priority,
                    'due_date' => $dueDate,
                    'position' => $i,
                    'created_by' => $alaa->id,
                    'assigned_to' => $assigneeId,
                    'completed_at' => $completedAt,
                    'started_at' => $startedAt,
                ]);
            }
        }
    }
}
