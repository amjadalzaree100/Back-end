<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\AddGroupMemberRequest;
use App\Http\Requests\Group\SetGroupManagerRequest;
use App\Http\Requests\Group\TransferManagerRequest;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GroupMemberController extends Controller
{
    public function index(Project $project, Group $group, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $canAccess = $project->isOwner($userId) || $group->isMember($userId) || $group->isManager($userId);

        if (!$canAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this group'
            ], 403);
        }

        $members = $group->members()->with('profile')->get();
        $manager = $group->manager()->with('profile')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'manager' => $manager ? new UserResource($manager) : null,
                'members' => UserResource::collection($members),
                'total_members' => $members->count() + ($manager ? 1 : 0)
            ]
        ]);
    }

    public function addMember(AddGroupMemberRequest $request, Project $project, Group $group): JsonResponse
    {
        $userId = $request->user()->id;
        $newMemberId = $request->user_id;

        if (!$group->project->isOwner($userId) && !$group->isManager($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to add members'
            ], 403);
        }

        if (!$group->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify members of an inactive group.'
            ], 422);
        }

        $projectUsers = $project->users()->pluck('users.id')->toArray();
        $projectUsers[] = $project->created_by;

        if (!in_array($newMemberId, $projectUsers)) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this project'
            ], 422);
        }

        if ($group->isMember($newMemberId)) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member of this group'
            ], 409);
        }

        try {
            DB::beginTransaction();

            $group->addMember($newMemberId, $userId);

            DB::commit();

            $newMember = $group->members()->with('profile')->find($newMemberId);

            return response()->json([
                'success' => true,
                'message' => 'Member added successfully',
                'data' => new UserResource($newMember)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeMember(Project $project, Group $group, int $userId, Request $request): JsonResponse
    {
        $currentUserId = $request->user()->id;

        if (!$project->isOwner($currentUserId) && !$group->isManager($currentUserId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to remove members'
            ], 403);
        }

        if (!$group->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify members of an inactive group.'
            ], 422);
        }

        if ($group->manager_id === $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the group manager. Transfer ownership first'
            ], 403);
        }

        if ($currentUserId === $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Use leave endpoint to leave the group'
            ], 422);
        }

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this group'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $group->removeMember($userId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function leaveGroup(Project $project, Group $group, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        if ($group->manager_id === $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Manager cannot leave the group. Transfer ownership first'
            ], 403);
        }

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this group'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $group->removeMember($userId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'You have left the group successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to leave group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function transferManager(TransferManagerRequest $request, Project $project, Group $group): JsonResponse
    {
        $currentUserId = $request->user()->id;
        $newManagerId = $request->route('userId') ?? $request->new_manager_id;

        // Validate the new manager is not the project owner
        if ($project->isOwner($newManagerId)) {
            return response()->json([
                'success' => false,
                'message' => 'The project owner cannot be a group manager.'
            ], 422);
        }

        // Validate the new manager has the 'manager' role in the project
        $newManagerRole = $project->getUserRole($newManagerId);
        if ($newManagerRole !== 'manager') {
            return response()->json([
                'success' => false,
                'message' => 'The selected user must have the manager role in this project.'
            ], 422);
        }

        if (!$project->isOwner($currentUserId) && !$group->isManager($currentUserId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to transfer manager role'
            ], 403);
        }

        if (!$group->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify members of an inactive group.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $group->transferManagerShip($newManagerId);

            DB::commit();

            $group->load(['manager', 'members']);

            return response()->json([
                'success' => true,
                'message' => 'Manager role transferred successfully',
                'data' => [
                    'new_manager' => $group->manager->name,
                    'old_manager' => $request->user()->name
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer manager role',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get the current manager of a group
     */
    public function getManager(Project $project, Group $group, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Check access: project owner, group member, or group manager
        if (!$project->isOwner($userId) && !$group->isMember($userId) && !$group->isManager($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this group'
            ], 403);
        }

        $manager = $group->manager()->with('profile')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'manager' => $manager ? new UserResource($manager) : null,
                'has_manager' => !is_null($manager),
            ]
        ]);
    }

    /**
     * Set or change the group manager
     * The user must be a member of the group first
     */
    public function setManager(SetGroupManagerRequest $request, Project $project, Group $group): JsonResponse
    {
        $userId = $request->user_id;
        $currentUserId = $request->user()->id;

        // Validate the new manager has the 'manager' role in the project
        $newManagerRole = $project->getUserRole($userId);
        if ($newManagerRole !== 'manager') {
            return response()->json([
                'success' => false,
                'message' => 'The selected user must have the manager role in this project.'
            ], 422);
        }

        if (!$group->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify members of an inactive group.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update the group manager
            $group->update(['manager_id' => $userId]);

            // If the new manager is not already a member, add them
            if (!$group->isMember($userId)) {
                $group->addMember($userId, $currentUserId);
            }


            DB::commit();

            $group->load(['manager', 'members']);

            return response()->json([
                'success' => true,
                'message' => 'Group manager assigned successfully',
                'data' => [
                    'manager' => $group->manager ? new UserResource($group->manager) : null,
                    'manager_id' => $group->manager_id,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to set group manager: ' . $e->getMessage(), [
                'group_id' => $group->id,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign manager. Please try again later.'
            ], 500);
        }
    }
}
