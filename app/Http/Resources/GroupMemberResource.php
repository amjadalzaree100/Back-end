<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'avatar' => $this->user?->profile?->avatar,
                'job_title' => $this->user?->profile?->job_title,
                'deleted' => $this->user?->deleted,
            ],
            'added_by' => [
                'id' => $this->addedBy?->id,
                'name' => $this->addedBy?->name,
                'deleted' => $this->addedBy?->deleted,
            ],
            'joined_at' => $this->joined_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
