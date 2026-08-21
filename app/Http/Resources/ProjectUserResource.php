<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->id,
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'avatar' => $this->profile?->avatar,
                'deleted' => $this->deleted,
            ],
            'role' => $this->pivot?->role ?? $this->role,
            'joined_at' => $this->pivot?->created_at?->toISOString() ?? $this->created_at?->toISOString(),
            'is_owner' => ($this->pivot?->role === 'owner' || $this->role === 'owner'),
        ];
    }
}
