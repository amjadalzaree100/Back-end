<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
        $userAssignment = $this->taskAssignments?->firstWhere('user_id', $userId);

        $displayType = $this->getDisplayType();

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),

            'type' => $displayType,
            'type_label' => $this->getDisplayTypeLabel(),

            // Group relations
            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', function () {
                return [
                    'id' => $this->group->id,
                    'name' => $this->group->name,
                ];
            }),

            'parent_task_id' => $this->parent_task_id,
            'parent_task' => $this->whenLoaded('parentTask', function () {
                return [
                    'id' => $this->parentTask->id,
                    'title' => $this->parentTask->title,
                ];
            }),

            'subtasks' => $this->whenLoaded('subTasks', function () {
                return TaskResource::collection($this->subTasks->load(['status', 'assignee', 'taskAssignments']));
            }),
            'subtasks_count' => $this->subTasks->count(),

            'allow_subtasks' => $this->allow_subtasks,
            'auto_status' => $this->auto_status,
            'can_be_assigned' => $this->can_be_assigned,

            'status_id' => $this->status_id,
            'status' => $this->whenLoaded('status', function () {
                return [
                    'id' => $this->status->id,
                    'name' => $this->status->name,
                    'position' => $this->status->position,
                ];
            }),
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'priority_color' => $this->priority_color,
            'due_date' => $this->due_date?->toISOString(),
            'due_date_formatted' => $this->due_date_formatted,
            'is_overdue' => $this->is_overdue,

            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', function () {
                return [
                    'id' => $this->assignee->id,
                    'name' => $this->assignee->name,
                    'avatar' => $this->assignee->profile?->avatar,
                ];
            }),
            'assigned_group_id' => $this->assigned_group_id,
            'assigned_group' => $this->whenLoaded('assignedGroup', function () {
                return [
                    'id' => $this->assignedGroup->id,
                    'name' => $this->assignedGroup->name,
                ];
            }),

            'assignments' => $this->whenLoaded('taskAssignments', function () {
                return $this->taskAssignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'user_id' => $assignment->user_id,
                        'user' => [
                            'id' => $assignment->user->id,
                            'name' => $assignment->user->name,
                            'avatar' => $assignment->user->profile?->avatar,
                        ],
                        'status_id' => $assignment->status_id,
                        'status' => $assignment->status?->name,
                        'started_at' => $assignment->started_at?->toISOString(),
                        'completed_at' => $assignment->completed_at?->toISOString(),
                        'is_completed' => !is_null($assignment->completed_at),
                    ];
                });
            }),
            'assignments_count' => $this->taskAssignments->count(),

            'my_assignment' => $userAssignment ? [
                'id' => $userAssignment->id,
                'status_id' => $userAssignment->status_id,
                'status' => $userAssignment->status?->name,
                'started_at' => $userAssignment->started_at?->toISOString(),
                'completed_at' => $userAssignment->completed_at?->toISOString(),
                'is_completed' => !is_null($userAssignment->completed_at),
            ] : null,

            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'avatar' => $this->creator->profile?->avatar,
                ];
            }),

            'position' => $this->position,

            'is_completed' => $this->isCompleted(),
            'is_started' => $this->isStarted(),
            'is_blocked' => $this->is_blocked,
            'can_be_started' => $this->can_be_started,
            'can_be_completed' => $this->can_be_completed,

            'started_at' => $this->started_at?->toISOString(),
            'started_at_formatted' => $this->started_at_formatted,
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'dependencies_count' => $this->dependencies_count,
            'dependents_count' => $this->dependents_count,
            'comments_count' => $this->whenCounted('comments', $this->comments_count ?? 0),
            'is_archived' => $this->is_archived,

        ];
    }

    protected function getDisplayType(): string
    {
        if ($this->isSubTask()) {
            return 'subtask';
        }

        if ($this->isGroupTask()) {
            return 'groupTask';
        }

        if ($this->isManagerTask()) {
            return 'managerTask';
        }

        return 'projectTask';
    }

    protected function getDisplayTypeLabel(): string
    {
        return match ($this->getDisplayType()) {
            'projectTask' => 'Project Task',
            'groupTask' => 'Group Task',
            'managerTask' => 'Manager Task',
            'subtask' => 'Subtask',
            default => 'Task',
        };
    }
}
