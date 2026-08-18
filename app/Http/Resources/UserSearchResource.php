<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->profile?->avatar,
            'job_title' => $this->profile?->job_title,
            'match_score' => $this->match_score ?? 0,
        ];
    }
}
