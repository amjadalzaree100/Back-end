<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreProfileRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function store(StoreProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile already exists. Use update instead.'
            ], 409);
        }

        try {
            DB::beginTransaction();

            $profile = $user->profile()->create(
                $request->validated()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile created successfully',
                'data' => new ProfileResource($profile->load('user'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the profile.'
            ], 500);
        }
    }
    public function show(Request $request, Profile $profile): JsonResponse|ProfileResource
    {
        try {
            $profile->load('user.ownedProjects', 'user.projects');
            return new ProfileResource($profile);
        } catch (\Exception $e) {
            Log::error('Profile show failed: ' . $e->getMessage(), [
                'profile_id' => $profile->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load profile. Please try again later.'
            ], 500);
        }
    }

    public function update(UpdateProfileRequest $request, Profile $profile): JsonResponse
    {
        if ($request->user()->id !== $profile->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own profile.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $profile->update($validated);

            DB::commit();

            $request->attributes->set('profile_viewing_own', true);

            $profile->load('user.ownedProjects', 'user.projects');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => new ProfileResource($profile),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Profile update failed', [
                'user_id' => $request->user()->id,
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the profile.',
            ], 500);
        }
    }
    public function myProfile(Request $request): ProfileResource|JsonResponse
    {
        $profile = $request->user()->profile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No profile found for the current user'
            ], 404);
        }

        $request->attributes->set('profile_viewing_own', true);

        $profile->load('user.ownedProjects', 'user.projects');

        return new ProfileResource($profile);
    }
    public function blockUser(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $userToBlock = User::findOrFail($userId);

        if ($user->id === $userToBlock->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot block yourself',
            ], 400);
        }

        try {
            $blocked = $user->blockUser($userToBlock, $request->input('reason'));

            if (!$blocked) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already blocked',
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'User blocked successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Block user failed', [
                'user_id' => $user->id,
                'target_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while blocking the user',
            ], 500);
        }
    }
    public function unblockUser(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $userToUnblock = User::findOrFail($userId);

        try {
            $unblocked = $user->unblockUser($userToUnblock);

            if (!$unblocked) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not blocked',
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'User unblocked successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Unblock user failed', [
                'user_id' => $user->id,
                'target_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while unblocking the user',
            ], 500);
        }
    }
    public function getBlockedUsers(Request $request): JsonResponse
    {
        $blockedUsers = $request->user()
            ->blockedUsers()
            ->with('blockedUser.profile')
            ->get()
            ->map(function ($block) {
                return [
                    'id' => $block->blockedUser->id,
                    'name' => $block->blockedUser->name,
                    'username' => $block->blockedUser->username,
                    'avatar' => $block->blockedUser->profile?->avatar,
                    'blocked_at' => $block->created_at,
                    'reason' => $block->reason,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $blockedUsers
        ]);
    }
    public function canSendMessage(Request $request, Profile $profile): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $profile->user_id) {
            return response()->json(['can_message' => false, 'reason' => 'You cannot message yourself.']);
        }

        if (!$profile->is_public) {
            return response()->json(['can_message' => false, 'reason' => 'Profile is private']);
        }

        if (!$profile->allow_messages) {
            return response()->json(['can_message' => false, 'reason' => 'User has disabled messages']);
        }

        if ($currentUser->isBlocking($profile->user)) {
            return response()->json(['can_message' => false, 'reason' => 'You have blocked this user']);
        }

        if ($profile->user->isBlocking($currentUser)) {
            return response()->json(['can_message' => false, 'reason' => 'You are blocked by this user']);
        }

        return response()->json(['can_message' => true]);
    }
    public function canSendInvitation(Request $request, Profile $profile): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $profile->user_id) {
            return response()->json([
                'can_invite' => false,
                'reason' => 'You cannot invite yourself.',
            ]);
        }

        if (!$profile->is_public) {
            return response()->json([
                'can_invite' => false,
                'reason' => 'Profile is private',
            ]);
        }

        if (!$profile->allow_invitation_requests) {
            return response()->json([
                'can_invite' => false,
                'reason' => 'User has disabled invitation requests',
            ]);
        }

        if ($currentUser->isBlocking($profile->user)) {
            return response()->json([
                'can_invite' => false,
                'reason' => 'You have blocked this user',
            ]);
        }

        if ($profile->user->isBlocking($currentUser)) {
            return response()->json([
                'can_invite' => false,
                'reason' => 'You are blocked by this user',
            ]);
        }

        return response()->json([
            'can_invite' => true,
        ]);
    }


    public function addSkill(Request $request, Profile $profile): JsonResponse
    {
        if ($request->user()->id !== $profile->user_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'skill' => 'required|string|max:100',
            'rating' => 'sometimes|integer|min:1|max:10'
        ]);

        $rating = $request->input('rating', 5);
        $added = $profile->addSkill($request->skill, $rating);

        if (!$added) {
            return response()->json(['success' => false, 'message' => 'Skill already exists'], 409);
        }

        $skills = $profile->fresh()->skills;
        usort($skills, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));

        return response()->json([
            'success' => true,
            'message' => 'Skill added successfully',
            'data' => ['skills' => $skills]
        ], 201);
    }

    public function updateSkillRating(Request $request, Profile $profile, string $skill): JsonResponse
    {
        if ($request->user()->id !== $profile->user_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate(['rating' => 'required|integer|min:1|max:10']);

        $updated = $profile->updateSkillRating($skill, $request->rating);
        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Skill not found'], 404);
        }

        $skills = $profile->fresh()->skills;
        usort($skills, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));

        return response()->json([
            'success' => true,
            'message' => 'Skill rating updated successfully',
            'data' => ['skills' => $skills]
        ]);
    }
    public function removeSkill(Request $request, Profile $profile, string $skill): JsonResponse
    {
        if ($request->user()->id !== $profile->user_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $removed = $profile->removeSkill($skill);
        if (!$removed) {
            return response()->json(['success' => false, 'message' => 'Skill not found'], 404);
        }

        $skills = $profile->fresh()->skills;
        usort($skills, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));

        return response()->json([
            'success' => true,
            'message' => 'Skill removed successfully',
            'data' => ['skills' => $skills]
        ]);
    }

    public function getSkills(Request $request, Profile $profile): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user && $user->id === $profile->user_id;

        if (!$profile->is_public && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized'
            ], 403);
        }

        $skills = $profile->skills ?? [];
        usort($skills, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));

        return response()->json([
            'success' => true,
            'data' => [
                'skills' => $skills
            ]
        ]);
    }
}
