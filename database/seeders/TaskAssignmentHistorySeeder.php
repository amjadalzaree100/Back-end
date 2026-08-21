<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TaskAssignmentHistorySeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::all();

        if ($tasks->isEmpty()) {
            return;
        }

        $memberMap = [];
        $managerMap = [];

        foreach (Project::with('users')->get() as $project) {
            $memberMap[$project->id] = $project->users->pluck('id')->toArray();
            $managerMap[$project->id] = $project->users
                ->filter(fn ($user) => in_array($user->pivot->role, ['owner', 'manager']))
                ->pluck('id')
                ->toArray();
        }

        $tasks = $tasks->shuffle();

        $target = rand(28, 55);
        $total = 0;

        foreach ($tasks as $task) {
            if ($total >= $target) {
                break;
            }

            $records = $this->recordsFor($task, $memberMap, $managerMap);

            foreach ($records as $data) {
                TaskAssignmentHistory::factory()->create($data);
            }

            $total += count($records);
        }

        // Top up until we reach the minimum of 30 records.
        $guard = 0;
        $i = 0;

        while ($total < 30 && $guard < $tasks->count() * 3) {
            $task = $tasks[$i % $tasks->count()];
            $cycle = $this->cycleFor($task, $memberMap, $managerMap);

            if (! empty($cycle)) {
                foreach ($cycle as $data) {
                    TaskAssignmentHistory::factory()->create($data);
                }

                $total += count($cycle);
            }

            $i++;
            $guard++;
        }
    }

    protected function recordsFor(Task $task, array $memberMap, array $managerMap): array
    {
        $members = $memberMap[$task->project_id] ?? [];

        if (empty($members)) {
            return [];
        }

        $assignedBy = $this->assignedByFor($task, $managerMap[$task->project_id] ?? [], $members);
        $base = $task->created_at ?? now();

        $records = [];

        if ($task->assigned_to) {
            if (rand(1, 100) <= 60) {
                $records[] = $this->record($task, $task->assigned_to, 'assigned', $assignedBy, $base, rand(0, 1));
            } else {
                $previous = $members[array_rand($members)];

                if ($previous === $task->assigned_to) {
                    $previous = $members[array_rand($members)];
                }

                $records[] = $this->record($task, $previous, 'unassigned', $assignedBy, $base, 1);
                $records[] = $this->record($task, $task->assigned_to, 'assigned', $assignedBy, $base, 3);
            }

            if (rand(1, 100) <= 25) {
                $temporary = $members[array_rand($members)];

                $records[] = $this->record($task, $temporary, 'unassigned', $assignedBy, $base, 5);
                $records[] = $this->record($task, $task->assigned_to, 'assigned', $assignedBy, $base, 7);
            }
        } elseif (rand(1, 100) <= 60) {
            $who = $members[array_rand($members)];

            $records[] = $this->record($task, $who, 'assigned', $assignedBy, $base, 1);
            $records[] = $this->record($task, $who, 'unassigned', $assignedBy, $base, 4);
        }

        return $this->finalize($records);
    }

    protected function cycleFor(Task $task, array $memberMap, array $managerMap): array
    {
        $members = $memberMap[$task->project_id] ?? [];

        if (empty($members)) {
            return [];
        }

        $assignedBy = $this->assignedByFor($task, $managerMap[$task->project_id] ?? [], $members);
        $base = $task->created_at ?? now();

        if ($task->assigned_to) {
            $previous = $members[array_rand($members)];

            if ($previous === $task->assigned_to) {
                $previous = $members[array_rand($members)];
            }

            $records = [
                $this->record($task, $previous, 'unassigned', $assignedBy, $base, rand(0, 1)),
                $this->record($task, $task->assigned_to, 'assigned', $assignedBy, $base, rand(2, 3)),
            ];
        } else {
            $who = $members[array_rand($members)];

            $records = [
                $this->record($task, $who, 'assigned', $assignedBy, $base, rand(0, 1)),
                $this->record($task, $who, 'unassigned', $assignedBy, $base, rand(2, 3)),
            ];
        }

        return $this->finalize($records);
    }

    protected function record(Task $task, int $userId, string $action, int $assignedBy, Carbon $base, int $days): array
    {
        $assignedAt = (clone $base)->addDays($days)->addHours(rand(0, 12))->addMinutes(rand(0, 59));

        return [
            'task_id' => $task->id,
            'user_id' => $userId,
            'assigned_by' => $assignedBy,
            'action' => $action,
            'assigned_at' => $assignedAt,
        ];
    }

    protected function finalize(array $records): array
    {
        if (empty($records)) {
            return $records;
        }

        $latest = $records[0]['assigned_at'];

        foreach ($records as $record) {
            if ($record['assigned_at']->gt($latest)) {
                $latest = $record['assigned_at'];
            }
        }

        if ($latest->gt(now())) {
            $shift = $latest->diffInSeconds(now());

            foreach ($records as $index => $record) {
                $records[$index]['assigned_at'] = $record['assigned_at']->subSeconds($shift);
            }
        }

        return $records;
    }

    protected function assignedByFor(Task $task, array $managerIds, array $memberIds): int
    {
        if (! empty($managerIds)) {
            return $managerIds[array_rand($managerIds)];
        }

        if (in_array($task->created_by, $memberIds)) {
            return $task->created_by;
        }

        if (! empty($memberIds)) {
            return $memberIds[array_rand($memberIds)];
        }

        return $task->created_by;
    }
}