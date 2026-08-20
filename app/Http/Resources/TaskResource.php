<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayType = $this->getDisplayType();

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),

            'type' => $displayType,
            'type_label' => $this->getDisplayTypeLabel(),

            'parent_task_id' => $this->parent_task_id,
            'parent_task' => $this->whenLoaded('parentTask', function () {
                return [
                    'id' => $this->parentTask->id,
                    'title' => $this->parentTask->title,
                ];
            }),

            'subtasks' => $this->whenLoaded('subTasks', function () {
                return TaskResource::collection($this->subTasks->load(['status', 'assignee']));
            }),
            'subtasks_count' => $this->subTasks->count(),

            'allow_subtasks' => $this->allow_subtasks,
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

            'assignments_count' => $this->assigned_to ? 1 : 0,

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

        return 'projectTask';
    }

    protected function getDisplayTypeLabel(): string
    {
        return match ($this->getDisplayType()) {
            'projectTask' => 'Project Task',
            'groupTask' => 'Group Task',
            'subtask' => 'Subtask',
            default => 'Task',
        };
    }
}
