<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTransfer;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskTransferSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::with('taskStatuses', 'users')->get();

        if ($projects->count() < 2) {
            return;
        }

        $alaa = User::where('email', 'alaa.gbh0@gmail.com')->first();
        $fallbackUser = User::query()->value('id');

        $memberMap = [];
        $statusMap = [];

        foreach ($projects as $project) {
            $memberMap[$project->id] = $project->users->pluck('id')->toArray();
            $statusMap[$project->id] = $project->taskStatuses->keyBy('name');
        }

        $notes = [
            'Moving to appropriate project',
            'Better fit for this team',
            'Consolidating related tasks',
            'Task belongs to the scope of the target project',
            'Reallocated during project restructuring',
        ];

        $tasks = Task::all()->shuffle();
        $transferCount = rand(3, 5);
        $performed = 0;

        foreach ($tasks as $task) {
            if ($performed >= $transferCount) {
                break;
            }

            $fromProjectId = $task->project_id;

            $candidates = $projects
                ->filter(fn (Project $project) => $project->id !== $fromProjectId)
                ->pluck('id')
                ->toArray();

            if (empty($candidates)) {
                continue;
            }

            $toProjectId = $candidates[array_rand($candidates)];

            $shared = array_values(array_intersect(
                $memberMap[$fromProjectId] ?? [],
                $memberMap[$toProjectId] ?? []
            ));

            shuffle($shared);

            $transferredBy = ! empty($shared)
                ? $shared[0]
                : ($alaa?->id ?? $fallbackUser);

            if (! $transferredBy) {
                continue;
            }

            $oldStatusName = $task->status?->name;

            $newStatus = $statusMap[$toProjectId][$oldStatusName]
                ?? $statusMap[$toProjectId]['To Do']
                ?? null;

            TaskTransfer::factory()->create([
                'task_id' => $task->id,
                'from_project_id' => $fromProjectId,
                'to_project_id' => $toProjectId,
                'from_task_id' => $task->id,
                'to_task_id' => $task->id,
                'transferred_by' => $transferredBy,
                'note' => $notes[array_rand($notes)],
                'transferred_at' => now()->subDays(rand(5, 60))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
            ]);

            $update = [
                'project_id' => $toProjectId,
                'transferred_from_task_id' => $task->id,
                'transferred_to_task_id' => $task->id,
            ];

            if ($newStatus) {
                $update['status_id'] = $newStatus->id;
            }

            $task->update($update);

            $performed++;
        }
    }
}