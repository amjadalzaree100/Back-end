<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'favorite_user' => [
                'id' => $this->favoriteUser->id,
                'name' => $this->favoriteUser->name,
                'username' => $this->favoriteUser->username,
                'avatar' => $this->favoriteUser->profile?->avatar,
                'job_title' => $this->favoriteUser->profile?->job_title,
                'is_public' => $this->favoriteUser->profile?->is_public,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
