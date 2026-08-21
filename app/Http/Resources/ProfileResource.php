<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();                                         // Get the currently authenticated user (or null if guest)
        $isOwner = $currentUser && $currentUser->id === $this->user_id;          // Check if the current user is the owner of this profile
        $viewingOwn = $request->attributes->get('profile_viewing_own', false);   // Determine if the current user is viewing their own profile
        $isPublic = $this->is_public;                                            // Determine if the profile is marked as public (visible to all) or private
        $isAccountDeactivated = !$this->user->is_active;                        // Check if the associated user account is deactivated (is_active = false)


        if ($isAccountDeactivated && !$isOwner && !$viewingOwn) {
            return [
                'id' => $this->id,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'deleted' => $this->user->deleted,
                ],
                'ratings_count' => $this->user->ratings_count,
                'ratings_average' => $this->user->ratings_average,
                'is_active' => false,
                'account_status' => 'deactivated',
                'message' => __('messages.account_deactivated_other'), // أو النص الثابت
            ];
        }




        if ($currentUser && !$isOwner && $this->user && $this->user->isBlocking($currentUser)) {
            return [
                'id' => $this->id,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                    'deleted' => $this->user->deleted,
                ],
                'ratings_count' => $this->user->ratings_count,
                'ratings_average' => $this->user->ratings_average,
                'blocked' => true,
                'message' => 'You have been blocked by this user.',
            ];
        }


        if (!$isPublic && !$viewingOwn) {
            return [
                'id' => $this->id,
                'avatar' => $this->avatar,
                'job_title' => $this->job_title,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                    'deleted' => $this->user->deleted,
                ],
                'ratings_count' => $this->user->ratings_count,
                'ratings_average' => $this->user->ratings_average,
                'is_public' => $this->is_public,
                'allow_messages' => $this->allow_messages,
                'allow_invitation_requests' => $this->allow_invitation_requests,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ];
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () use ($viewingOwn, $isPublic, $isOwner) {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                    'deleted' => $this->user->deleted,
                ];
            }),

            'phone' => $this->when($viewingOwn || $isOwner || $isPublic, $this->phone),
            'bio' => $this->when($viewingOwn || $isOwner || $isPublic, $this->bio),
            'job_title' => $this->job_title,
            'skills' => $this->when($viewingOwn || $isOwner || $isPublic, function () {
                $skills = $this->skills ?? [];
                usort($skills, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));
                return $skills;
            }),
            'avatar' => $this->avatar,
            'location' => $this->when($viewingOwn || $isOwner || $isPublic, $this->location),
            'alternative_email' => $this->when($viewingOwn || $isOwner, $this->alternative_email),

            'twitter_url' => $this->when($viewingOwn || $isOwner || $isPublic, $this->twitter_url),
            'facebook_url' => $this->when($viewingOwn || $isOwner || $isPublic, $this->facebook_url),
            'instagram_url' => $this->when($viewingOwn || $isOwner || $isPublic, $this->instagram_url),
            'youtube_url' => $this->when($viewingOwn || $isOwner || $isPublic, $this->youtube_url),
            'github_url' => $this->when($viewingOwn || $isOwner || $isPublic, $this->github_url),
            'portfolio_url' => $this->when($viewingOwn || $isOwner || $isPublic, $this->portfolio_url),
            'linkedin_url' => $this->when($viewingOwn || $isOwner || $isPublic, $this->linkedin_url),
            'cv_url' => $this->when($viewingOwn || $isOwner || $isPublic, $this->cv_url),

            'language' => $this->when($viewingOwn || $isOwner || $isPublic, $this->language),
            'theme' => $this->when($viewingOwn || $isOwner || $isPublic, $this->theme),

            'is_public' => $this->is_public,
            'allow_messages' => $this->when($viewingOwn || $isOwner || $isPublic, $this->allow_messages),
            'allow_invitation_requests' => $this->when($viewingOwn || $isOwner  || $isPublic, $this->allow_invitation_requests),

            'projects' => $this->when($viewingOwn || $isOwner || $isPublic, function () use ($viewingOwn, $isOwner) {
                $ownedProjects = $this->user->ownedProjects ?? collect();
                $memberProjects = $this->user->projects ?? collect();

                $projects = $ownedProjects->merge($memberProjects)->unique('id');

                if (!$viewingOwn && !$isOwner) {
                    $projects = $projects->where('visibility', 'public');
                }

                return $projects->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'image' => $project->image,
                        'role' => $project->created_by === $this->user->id
                            ? 'owner'
                            : ($project->pivot->role ?? 'member'),
                    ];
                })->values();
            }),

            'projects_count' => $this->when($viewingOwn || $isOwner || $isPublic, $this->projects_count),
            'tasks_completed' => $this->when($viewingOwn || $isOwner || $isPublic, $this->tasks_completed),
            'report_count' => $this->when($viewingOwn || $isOwner, $this->report_count),

            'ratings_count' => $this->user->ratings_count,
            'ratings_average' => $this->user->ratings_average,

            'is_active' => $this->user->is_active,
            'account_status' => $this->user->is_active ? 'active' : 'deactivated',


            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
