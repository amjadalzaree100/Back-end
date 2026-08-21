<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use Illuminate\Database\Seeder;

class TaskStatusHistorySeeder extends Seeder
{
    protected array $allowedCounts = [
        'Done' => [2, 4, 6],
        'In Progress' => [1, 3, 5],
        'To Do' => [0, 1],
    ];

    public function run(): void
    {
        $tasks = Task::with('status')->get();

        if ($tasks->isEmpty()) {
            return;
        }

        $statusMap = [];
        $memberMap = [];
        $managerMap = [];

        foreach (Project::with('taskStatuses', 'users')->get() as $project) {
            $statusMap[$project->id] = $project->taskStatuses->keyBy('name');
            $memberMap[$project->id] = $project->users->pluck('id')->toArray();
            $managerMap[$project->id] = $project->users
                ->filter(fn ($user) => in_array($user->pivot->role, ['owner', 'manager']))
                ->pluck('id')
                ->toArray();
        }

        // Decide how many transitions each task gets based on its current status.
        $counts = [];
        $tasksById = [];

        foreach ($tasks as $task) {
            $statusName = $task->status?->name;

            if (! isset($this->allowedCounts[$statusName])) {
                continue;
            }

            $tasksById[$task->id] = $task;

            $counts[$task->id] = match ($statusName) {
                'Done' => rand(1, 100) <= 70 ? 2 : 4,
                'In Progress' => rand(1, 100) <= 70 ? 1 : 3,
                default => rand(1, 100) <= 55 ? 1 : 0,
            };
        }

        if (empty($counts)) {
            return;
        }

        // Grow sequence lengths until we reach the target total (50-100 records).
        $target = rand(55, 90);
        $total = array_sum($counts);

        $ids = array_keys($counts);
        shuffle($ids);

        $noProgress = 0;
        $i = 0;

        while ($total < $target && $noProgress < count($ids) * 2) {
            $id = $ids[$i % count($ids)];
            $statusName = $tasksById[$id]->status->name;
            $allowed = $this->allowedCounts[$statusName];
            $current = $counts[$id];

            $next = null;

            foreach ($allowed as $candidate) {
                if ($candidate > $current) {
                    $next = $candidate;
                    break;
                }
            }

            if ($next !== null) {
                $total += $next - $current;
                $counts[$id] = $next;
                $noProgress = 0;
            } else {
                $noProgress++;
            }

            $i++;
        }

        foreach ($counts as $taskId => $count) {
            if ($count === 0) {
                continue;
            }

            $task = $tasksById[$taskId];
            $statusName = $task->status->name;
            $projectStatuses = $statusMap[$task->project_id] ?? collect();

            $transitions = $this->transitionsFor($statusName, $count);
            $times = $this->transitionTimes($task, $statusName, count($transitions));

            foreach ($transitions as $index => [$fromName, $toName]) {
                TaskStatusHistory::factory()->create([
                    'task_id' => $taskId,
                    'from_status_id' => $projectStatuses[$fromName]?->id ?? null,
                    'to_status_id' => $projectStatuses[$toName]->id,
                    'changed_by' => $this->pickMember(
                        $managerMap[$task->project_id] ?? [],
                        $memberMap[$task->project_id] ?? []
                    ),
                    'changed_at' => $times[$index],
                ]);
            }
        }
    }

    protected function transitionsFor(string $status, int $count): array
    {
        $toDo = 'To Do';
        $inProgress = 'In Progress';
        $done = 'Done';

        if ($status === 'Done') {
            return match ($count) {
                4 => [[$toDo, $inProgress], [$inProgress, $toDo], [$toDo, $inProgress], [$inProgress, $done]],
                6 => [[$toDo, $inProgress], [$inProgress, $toDo], [$toDo, $inProgress], [$inProgress, $done], [$done, $inProgress], [$inProgress, $done]],
                default => [[$toDo, $inProgress], [$inProgress, $done]],
            };
        }

        if ($status === 'In Progress') {
            return match ($count) {
                3 => [[$toDo, $inProgress], [$inProgress, $toDo], [$toDo, $inProgress]],
                5 => [[$toDo, $inProgress], [$inProgress, $toDo], [$toDo, $inProgress], [$inProgress, $done], [$done, $inProgress]],
                default => [[$toDo, $inProgress]],
            };
        }

        // To Do (task was started then moved back)
        return match ($count) {
            1 => [[$inProgress, $toDo]],
            default => [[$toDo, $inProgress], [$inProgress, $toDo]],
        };
    }

    protected function transitionTimes(Task $task, string $status, int $count): array
    {
        $base = $task->started_at ?? $task->created_at ?? now();

        $times = [];
        $cursor = (clone $base)->addDays(rand(0, 2))->addHours(rand(0, 12));

        $times[] = $cursor;

        for ($i = 1; $i < $count; $i++) {
            $cursor = (clone $cursor)->addDays(rand(1, 4))->addHours(rand(0, 12));
            $times[] = $cursor;
        }

        $last = $times[$count - 1];

        if ($status === 'Done' && $task->completed_at) {
            $anchor = $task->completed_at;
        } else {
            $anchor = $last->gt(now()) ? now() : $last;
        }

        if ($anchor->lt($last)) {
            $shift = $last->diffInSeconds($anchor);

            foreach ($times as $index => $time) {
                $times[$index] = $time->subSeconds($shift);
            }
        }

        return $times;
    }

    protected function pickMember(array $managerIds, array $memberIds): ?int
    {
        $pool = ! empty($managerIds) ? $managerIds : $memberIds;

        if (empty($pool)) {
            return null;
        }

        return $pool[array_rand($pool)];
    }
}