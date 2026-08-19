<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'users' => UserSearchResource::collection($this['users']),
            'projects' => ProjectSearchResource::collection($this['projects']),
        ];
    }
}
