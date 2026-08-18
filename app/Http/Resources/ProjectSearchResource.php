<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'visibility' => $this->visibility,
            'visibility_label' => $this->visibility_label,
            'match_score' => $this->match_score ?? 0,
        ];
    }
}
