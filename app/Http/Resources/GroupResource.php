<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'is_active' => $this->is_active,
            'manager' => $this->whenLoaded('manager', function () {
                return $this->manager ? [
                    'id' => $this->manager->id,
                    'name' => $this->manager->name,
                    'avatar' => $this->manager->profile?->avatar,
                    'deleted' => $this->manager->deleted,
                ] : null;
            }),
            'creator' => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'deleted' => $this->creator?->deleted,
            ],
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'status' => $this->project->status,
                ];
            }),
            'members_count' => $this->members->count(),
            'tasks_count' => $this->groupTasks_count ?? $this->groupTasks->count(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
