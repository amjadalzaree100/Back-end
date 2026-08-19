<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'content' => $this->content,
            'user' => [
                'id' => $this->user->id ?? null,
                'name' => $this->user->name ?? 'Unknown',
                'avatar' => $this->user->profile?->avatar ?? null,
            ],
            'is_owner' => $this->user_id === ($request->user()?->id),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
