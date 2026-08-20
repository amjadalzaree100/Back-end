<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $project = $this->resource;

        $project->loadMissing(['users', 'tasks', 'taskStatuses', 'groups']);

        // ========== 1. Member Statistics ==========
        $members = $project->users;
        $totalMembers = $members->count();

        $rolesCount = [
            'owner' => 0,
            'manager' => 0,
            'user' => 0,
            'observer' => 0,
        ];

        foreach ($members as $member) {
            $rolesCount[$member->pivot->role]++;
        }

        // ========== 2. Task Statistics ==========
        $tasks = $project->tasks;
        $totalTasks = $tasks->count();

        $completedTasks = $tasks->whereNotNull('completed_at')->count();
        $pendingTasks = $totalTasks - $completedTasks;

        $assignedTasks = $tasks->filter(fn($task) =>
            !is_null($task->assigned_to) ||
            !is_null($task->assigned_group_id)
        )->count();

        $unassignedTasks = $totalTasks - $assignedTasks;

        // ========== 3. Tasks by priority ==========
        $tasksByPriority = [
            'urgent' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($tasks as $task) {
            if (isset($tasksByPriority[$task->priority])) {
                $tasksByPriority[$task->priority]++;
            }
        }

        // ========== 4. Tasks by status ==========
        $tasksByStatus = [];
        $statuses = $project->taskStatuses;

        foreach ($statuses as $status) {
            $tasksByStatus[] = [
                'status_id' => $status->id,
                'name' => $status->name,
                'count' => $tasks->where('status_id', $status->id)->count(),
            ];
        }

        return [
            'members' => [
                'total' => $totalMembers,
                'by_role' => $rolesCount,
            ],
            'tasks' => [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'pending' => $pendingTasks,
                'assigned' => $assignedTasks,
                'unassigned' => $unassignedTasks,
                'by_priority' => $tasksByPriority,
                'by_status' => $tasksByStatus,
            ],
        ];
    }
}
