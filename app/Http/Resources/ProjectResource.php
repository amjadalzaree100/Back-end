<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    protected bool $showFullDetails = true;

    public function setFullDetails(bool $value): self
    {
        $this->showFullDetails = $value;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwnerOrMember = ($this->created_by === $user?->id) || $this->users->contains('id', $user?->id);

        if ($this->visibility === 'private' && !$this->showFullDetails) {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'image' => $this->image,
                'status' => $this->status,
                'visibility' => $this->visibility,
                'user_role' => 'guest',
                'allow_commit' => $this->allow_commit,
                'allow_reactions' => $this->allow_reactions,
                'allow_join_requests' => $this->allow_join_requests,
                'reaction_counts' => $this->reaction_counts,
                'user_reaction' => $this->when(request()->user(), fn() => $this->user_reaction),
                'comments' => ProjectCommentResource::collection($this->whenLoaded('projectComments')),
                'chain' => $this->whenLoaded('chains', function () {
                    $chain = $this->chains->first();
                    return $chain ? [
                        'id' => $chain->id,
                        'name' => $chain->name,
                    ] : null;
                }),
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'username' => $this->creator->username,
                    'job_title' => $this->creator->profile?->job_title,
                    'avatar' => $this->creator->profile?->avatar,
                ];
            }),
            'users_count' => $this->whenCounted('users'),
            'tasks_count' => $this->whenCounted('tasks'),
            'user_role' => $this->user_role ?? 'guest',
            'is_owner' => $this->is_owner ?? false,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'reaction_counts' => $this->reaction_counts,
            'user_reaction' => $this->when(request()->user(), fn() => $this->user_reaction),
            'allow_commit' => $this->allow_commit,
            'allow_reactions' => $this->allow_reactions,
            'allow_join_requests' => $this->allow_join_requests,
            'chain' => $this->whenLoaded('chains', function () {
                $chain = $this->chains->first();
                return $chain ? [
                    'id' => $chain->id,
                    'name' => $chain->name,
                ] : null;
            }),
        ];
    }
}
