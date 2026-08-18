<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project' => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'description' => $this->project->description,
                'image' => $this->project->image,
                'status' => $this->project->status,
                'visibility' => $this->project->visibility,
                'creator_name' => $this->project->creator?->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
