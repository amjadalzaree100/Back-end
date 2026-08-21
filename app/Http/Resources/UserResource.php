<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->when(
                $request->user() && $request->user()->id === $this->id,
                $this->email
            ),
            'avatar' => $this->profile?->avatar,
            'job_title' => $this->profile?->job_title,
            'is_active' => $this->is_active,
            'deleted' => $this->deleted,
            'email_verified' => !is_null($this->email_verified_at),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
