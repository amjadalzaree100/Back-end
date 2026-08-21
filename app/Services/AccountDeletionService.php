<?php

namespace App\Services;

use App\Events\ProjectNotificationEvent;
use App\Models\BlockedUser;
use App\Models\FcmToken;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemberCleanupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountDeletionService
{
    public function __construct(
        private NotificationService $notificationService,
        private ProjectMemberCleanupService $cleanupService,
    ) {}

    /**
     * Delete a user's account (soft-delete).
     *
     * @throws \Exception if user owns projects
     */
    public function deleteAccount(User $user, string $password): void
    {
        // 1. Verify password
        if (!Hash::check($password, $user->password)) {
            throw new \Illuminate\Validation\ValidationException(
                ['password' => ['The provided password is incorrect.']],
            );
        }

        // 2. Block if user owns any projects
        $ownedProjects = Project::where('created_by', $user->id)->count();
        if ($ownedProjects > 0) {
            abort(403, 'You must delete all your projects before deleting your account.');
        }

        DB::transaction(function () use ($user) {
            // 3. Leave all projects and handle per-role cleanup
            $this->leaveAllProjects($user);

            // 4. Clean up personal data
            $this->cleanupPersonalData($user);

            // 5. Reset profile
            $this->resetProfile($user);

            // 6. Soft-delete user
            $user->delete();
        });
    }

    private function leaveAllProjects(User $user): void
    {
        $projects = $user->projects()->withTrashed()->get();

        foreach ($projects as $project) {
            $role = $user->projects()->where('project_id', $project->id)->first()?->pivot?->role;

            // Per-role cleanup
            if ($role === 'manager') {
                $this->cleanupManagerRole($user, $project);
            } elseif ($role === 'user') {
                $this->cleanupUserRole($user, $project);
            }
            // observer: just leave, no extra cleanup

            // Remove from project_users pivot
            $project->users()->detach($user->id);

            // Also remove from any group memberships in this project
            $project->groups()->each(function ($group) use ($user) {
                $group->members()->where('user_id', $user->id)->delete();
            });

            // Decrement projects_count on profile
            $user->profile()?->decrement('projects_count');

            // Notify project owner
            event(new ProjectNotificationEvent(
                userIds: [$project->created_by],
                scenario: 'user_left',
                project: $project,
                actor: $user,
            ));
        }
    }

    private function cleanupManagerRole(User $user, Project $project): void
    {
        $this->cleanupService->cleanupManagerRole($user, $project);
    }

    private function cleanupUserRole(User $user, Project $project): void
    {
        $this->cleanupService->cleanupUserRole($user, $project);
    }

    private function cleanupPersonalData(User $user): void
    {
        // Delete notifications
        $user->notifications()->delete();

        // Delete reminders (and detach from tasks)
        $user->reminders()->each(function ($reminder) {
            $reminder->tasks()->detach();
            $reminder->delete();
        });

        // Delete notes
        $user->note()?->delete();

        // Delete FCM tokens
        FcmToken::where('user_id', $user->id)->delete();

        // Delete Sanctum tokens (revoke all sessions)
        $user->tokens()->delete();

        // Delete favorites (user ↔ user)
        $user->favoriteUsers()->detach();
        $user->favoritedBy()->detach();

        // Delete favorite projects
        $user->favoriteProjects()->detach();

        // Delete blocked users
        $user->blockedUsers()->delete();

        // Also remove rows where this user is the blocked one
        BlockedUser::where('blocked_user_id', $user->id)->delete();

        // Delete pending requests (sent and received)
        $user->sentRequests()->where('status', 'pending')->delete();
        $user->receivedRequests()->where('status', 'pending')->delete();
    }

    private function resetProfile(User $user): void
    {
        $profile = $user->profile;
        if ($profile) {
            $profile->update([
                'phone' => null,
                'bio' => null,
                'job_title' => null,
                'skills' => null,
                'avatar' => null,
                'location' => null,
                'alternative_email' => null,
                'twitter_url' => null,
                'facebook_url' => null,
                'instagram_url' => null,
                'youtube_url' => null,
                'github_url' => null,
                'portfolio_url' => null,
                'linkedin_url' => null,
                'cv_url' => null,
                'language' => 'ar',
                'theme' => 'light',
                'is_public' => false,
                'allow_messages' => false,
                'allow_invitation_requests' => false,
                'projects_count' => 0,
                'tasks_completed' => 0,
                'report_count' => 0,
            ]);
        }
    }
}
