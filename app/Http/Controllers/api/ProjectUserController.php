<?php

namespace App\Http\Controllers\api;

use App\Events\ProjectNotificationEvent;
use App\Events\UserJoinedProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectUser\AddUserRequest;
use App\Http\Requests\ProjectUser\UpdateUserRoleRequest;
use App\Http\Resources\ProjectUserResource;
use App\Models\Chain;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemberCleanupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectUserController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            if (!$project->isOwner($userId) && !$project->hasUser($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project.'
                ], 403);
            }

            $users = $project->users()
                ->with('profile')
                ->get()
                ->map(function ($user) use ($project) {
                    $user->role = $user->pivot->role;
                    return $user;
                });

            $owner = null;
            $managers = [];
            $usersList = [];
            $observers = [];

            foreach ($users as $user) {
                $role = $user->pivot->role;

                if ($role === 'owner') {
                    $owner = $user;
                } elseif ($role === 'manager') {
                    $managers[] = $user;
                } elseif ($role === 'user') {
                    $usersList[] = $user;
                } elseif ($role === 'observer') {
                    $observers[] = $user;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'owner' => $owner ? new ProjectUserResource($owner) : null,
                    'managers' => ProjectUserResource::collection($managers),
                    'users' => ProjectUserResource::collection($usersList),
                    'observers' => ProjectUserResource::collection($observers),
                    'total' => $users->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch project users: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load users. Please try again later.'
            ], 500);
        }
    }

    public function addUser(AddUserRequest $request, Project $project): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $currentUserRole = $project->getUserRole($userId);

            // Check permission
            if ($project->created_by !== $userId && !in_array($currentUserRole, ['owner', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to add users to this project'
                ], 403);
            }

            $newUserId = $request->user_id;

            if ($project->hasUser($newUserId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already a member of this project'
                ], 409);
            }

            $newUser = User::find($newUserId);
            if (!$newUser || !$newUser->profile || !$newUser->profile->allow_invitation_requests) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user does not accept invitations.'
                ], 422);
            }

            DB::beginTransaction();

            $project->addUser($newUserId, $request->role);

            DB::commit();

            $newUser = User::with('profile')->find($newUserId);
            $newUser->role = $request->role;

            event(new UserJoinedProject($newUser, $project));

            ProjectNotificationEvent::dispatch(
                userIds: [$newUserId],
                scenario: 'user_added',
                project: $project,
                actor: $request->user(),
            );

            return response()->json([
                'success' => true,
                'message' => 'User added successfully',
                'data' => new ProjectUserResource($newUser)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add user: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'new_user_id' => $request->user_id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add user. Please try again later.'
            ], 500);
        }
    }
    public function updateRole(UpdateUserRoleRequest $request, Project $project, int $userId, ProjectMemberCleanupService $cleanupService): JsonResponse
    {
        $currentUserId = $request->user()->id;
        $currentUserRole = $project->getUserRole($currentUserId);
        $targetUserRole = $project->getUserRole($userId);
        $newRole = $request->role;

        if ($project->created_by !== $currentUserId && !in_array($currentUserRole, ['owner', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update user roles'
            ], 403);
        }

        if (!$project->hasUser($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this project'
            ], 404);
        }
        if ($targetUserRole === $newRole) {
            return response()->json([
                'success' => false,
                'message' => 'User already has this role.'
            ], 422);
        }

        if ($targetUserRole === 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change the role of the project owner'
            ], 403);
        }

        if ($currentUserRole === 'manager' && $targetUserRole === 'manager') {
            return response()->json([
                'success' => false,
                'message' => 'Managers cannot change other managers roles'
            ], 403);
        }

        $isDemotingManager = ($targetUserRole === 'manager' && in_array($newRole, ['user', 'observer']));
        $managedGroupsCount = 0;

        try {
            DB::beginTransaction();

            if ($isDemotingManager) {
                $targetUser = User::find($userId);
                $managedGroupsCount = $cleanupService->countManagedGroups($targetUser, $project);

                \App\Models\Group::where('project_id', $project->id)
                    ->where('manager_id', $userId)
                    ->update(['manager_id' => null]);
            }

            $project->updateUserRole($userId, $newRole);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user role: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $userId,
                'new_role' => $newRole
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role. Please try again later.'
            ], 500);
        }

        $project->load('users');
        $updatedUser = User::with('profile')->find($userId);
        $updatedUser->role = $newRole;

        if ($isDemotingManager) {
            ProjectNotificationEvent::dispatch(
                userIds: [$currentUserId],
                scenario: 'manager_demoted',
                project: $project,
                actor: $request->user(),
                extra: [
                    'demoted_user_name' => $updatedUser->name,
                    'new_role' => $newRole,
                    'group_count' => $managedGroupsCount,
                ],
            );

            ProjectNotificationEvent::dispatch(
                userIds: [$userId],
                scenario: 'manager_demoted_self',
                project: $project,
                actor: $request->user(),
                extra: [
                    'new_role' => $newRole,
                    'group_count' => $managedGroupsCount,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => new ProjectUserResource($updatedUser)
        ]);
    }
    public function removeUser(Request $request, Project $project, int $userId, ProjectMemberCleanupService $cleanupService): JsonResponse
    {
        try {
            $currentUserId = $request->user()->id;
            $currentUserRole = $project->getUserRole($currentUserId);

            if ($project->created_by !== $currentUserId && !in_array($currentUserRole, ['owner', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to remove users'
                ], 403);
            }

            if (!$project->hasUser($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a member of this project'
                ], 404);
            }

            $targetUserRole = $project->getUserRole($userId);

            if ($targetUserRole === 'owner') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove the project owner'
                ], 403);
            }

            if ($currentUserRole === 'manager' && $targetUserRole === 'manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Managers cannot remove other managers'
                ], 403);
            }

            if ($currentUserId === $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot remove yourself from the project'
                ], 403);
            }

            $managedGroupsCount = 0;

            DB::beginTransaction();

            if ($targetUserRole === 'manager') {
                $targetUser = User::find($userId);
                $managedGroupsCount = $cleanupService->cleanupManagerRole($targetUser, $project);
            } elseif ($targetUserRole === 'user') {
                $targetUser = User::find($userId);
                $cleanupService->cleanupUserRole($targetUser, $project);
            }

            $project->removeUser($userId);

            DB::commit();

            ProjectNotificationEvent::dispatch(
                userIds: [$userId],
                scenario: 'user_removed',
                project: $project,
                actor: $request->user(),
            );

            if ($targetUserRole === 'manager') {
                ProjectNotificationEvent::dispatch(
                    userIds: [$project->created_by],
                    scenario: 'manager_left_with_groups',
                    project: $project,
                    actor: User::find($userId),
                    extra: ['group_count' => $managedGroupsCount],
                );
            }

            if (!$cleanupService->hasManagers($project)) {
                ProjectNotificationEvent::dispatch(
                    userIds: [$project->created_by],
                    scenario: 'project_no_managers',
                    project: $project,
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'User removed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove user: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $currentUserId ?? null,
                'removed_user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove user. Please try again later.'
            ], 500);
        }
    }
    public function leaveProject(Request $request, Project $project, ProjectMemberCleanupService $cleanupService): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            if ($project->created_by === $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project owner cannot leave the project. Transfer ownership first.'
                ], 403);
            }

            $userRole = $project->getUserRole($userId);
            $managedGroupsCount = 0;

            DB::beginTransaction();

            if ($userRole === 'manager') {
                $managedGroupsCount = $cleanupService->cleanupManagerRole($request->user(), $project);
            } elseif ($userRole === 'user') {
                $cleanupService->cleanupUserRole($request->user(), $project);
            }

            $project->removeUser($userId);

            DB::commit();

            if ($userRole === 'manager') {
                ProjectNotificationEvent::dispatch(
                    userIds: [$project->created_by],
                    scenario: 'manager_left_with_groups',
                    project: $project,
                    actor: $request->user(),
                    extra: ['group_count' => $managedGroupsCount],
                );
            } else {
                ProjectNotificationEvent::dispatch(
                    userIds: [$project->created_by],
                    scenario: 'user_left_project',
                    project: $project,
                    actor: $request->user(),
                );
            }

            if (!$cleanupService->hasManagers($project)) {
                ProjectNotificationEvent::dispatch(
                    userIds: [$project->created_by],
                    scenario: 'project_no_managers',
                    project: $project,
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'You have left the project successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to leave project: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $userId ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to leave project. Please try again later.'
            ], 500);
        }
    }
    public function transferOwnership(Request $request, Project $project, int $userId): JsonResponse
    {
        $currentUserId = $request->user()->id;

        if ($project->chains()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This project belongs to a chain. You cannot transfer ownership of a single project. Please transfer the entire chain instead.',
            ], 422);
        }

        if ($project->created_by !== $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Only the project owner can transfer ownership'
            ], 403);
        }


        if ($project->created_by !== $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Only the project owner can transfer ownership'
            ], 403);
        }

        if (!$project->hasUser($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a user of this project'
            ], 404);
        }

        if ($currentUserId === $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot transfer ownership to yourself'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $project->updateUserRole($currentUserId, 'user');
            $project->updateUserRole($userId, 'owner');
            $project->update(['created_by' => $userId]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer ownership',
                'error' => $e->getMessage()
            ], 500);
        }

        ProjectNotificationEvent::dispatch(
            userIds: [$currentUserId, $userId],
            scenario: 'ownership_transferred',
            project: $project,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Project ownership transferred successfully',
            'data' => [
                'new_owner_id' => $userId,
                'previous_owner_id' => $currentUserId,
            ]
        ]);
    }


public function transferChainOwnership(Request $request, Chain $chain, int $userId): JsonResponse
{
    $currentUserId = $request->user()->id;

    if ($chain->created_by !== $currentUserId) {
        return response()->json([
            'success' => false,
            'message' => 'Only the chain owner can transfer the entire chain.'
        ], 403);
    }

    $projects = $chain->projects;
    if ($projects->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'This chain has no projects to transfer.'
        ], 422);
    }

    $targetUser = User::findOrFail($userId);

    foreach ($projects as $project) {
        if (!$project->hasUser($userId)) {
            $project->addUser($userId, 'user');
        }
    }

    DB::beginTransaction();
    try {
        foreach ($projects as $project) {
            $project->update(['created_by' => $userId]);

            if ($project->hasUser($userId)) {
                $project->updateUserRole($userId, 'owner');
            }

            if ($project->hasUser($currentUserId)) {
                $project->updateUserRole($currentUserId, 'user');
            }
        }

        $chain->update(['created_by' => $userId]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Entire chain transferred successfully.',
            'data' => $chain->load('projects'),
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Chain transfer failed: ' . $e->getMessage(), [
            'chain_id' => $chain->id,
            'from_user' => $currentUserId,
            'to_user' => $userId,
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to transfer chain ownership. Please try again later.'
        ], 500);
    }
}
}
