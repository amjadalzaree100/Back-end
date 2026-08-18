<?php

namespace app\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GroupController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $search = $request->input('search');

            $query = Group::with(['manager', 'creator', 'members'])
                ->where('project_id', $project->id)
                ->active();

            // Apply permission filter
            $isOwner = $project->isOwner($userId);
            $isManager = $project->isManager($userId);

            if (!$isOwner && !$isManager) {
                // Regular members: only see groups they are members of
                $query->whereHas('members', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            }
            // If owner or manager, no extra filter needed (see all groups)

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('description', 'LIKE', '%' . $search . '%');
                });
            }

            $groups = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => GroupResource::collection($groups),
                'total' => $groups->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch groups: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load groups. Please try again later.'
            ], 500);
        }
    }
    public function show(Project $project, Group $group, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $canAccess = $project->isOwner($userId) || $group->isManager($userId);

        if (!$canAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this group'
            ], 403);
        }

        $group->load([
            'manager',
            'creator',
            'members',
            'groupTasks' => function ($query) {
                $query->whereNull('parent_task_id');
            }
        ]);

        return response()->json([
            'success' => true,
            'data' => new GroupResource($group)
        ]);
    }

    public function store(StoreGroupRequest $request, Project $project): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $managerId = $request->manager_id;

            // Ensure manager is a project member
            if (!$project->hasUser($managerId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected manager must be a member of this project.'
                ], 422);
            }

            // Validate additional members (if provided)
            $memberIds = $request->input('member_ids', []);
            if (!empty($memberIds)) {
                $invalidMembers = array_filter($memberIds, fn($id) => !$project->hasUser($id));
                if (!empty($invalidMembers)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some users are not members of this project: ' . implode(', ', $invalidMembers)
                    ], 422);
                }
            }

            DB::beginTransaction();

            // Create group
            $group = Group::create([
                'project_id' => $project->id,
                'name' => $request->name,
                'description' => $request->description,
                'avatar' => $request->avatar,
                'manager_id' => null,
                'created_by' => $userId,
                'is_active' => true,
            ]);


            // Add manager as member only if manager_id is provided
            if ($managerId) {
                $group->addMember($managerId, $userId);
            }
            // Add additional members
            if (!empty($memberIds)) {
                foreach (array_unique($memberIds) as $memberId) {
                    // Skip if already added (avoid duplicates)
                    if ($memberId !== $managerId && !$group->isMember($memberId)) {
                        $group->addMember($memberId, $userId);
                    }
                }
            }

            DB::commit();

            $group->load(['manager', 'creator', 'members']);

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully.',
                'data' => new GroupResource($group)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Group creation failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create group. Please try again later.'
            ], 500);
        }
    }
    public function update(Request $request, Project $project, Group $group): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            if ($group->project_id !== $project->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group does not belong to this project.'
                ], 404);
            }

            if (!$project->isOwner($userId) && !$group->isManager($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to update this group.'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|min:2',
                'description' => 'nullable|string|max:1000',
                'avatar' => 'nullable|string|max:255|url',
                'is_active' => 'sometimes|boolean',
            ]);

            DB::beginTransaction();

            $group->update($validated);

            DB::commit();

            $group->load(['manager', 'creator', 'members']);

            return response()->json([
                'success' => true,
                'message' => 'Group updated successfully.',
                'data' => new GroupResource($group)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update group: ' . $e->getMessage(), [
                'group_id' => $group->id,
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update group. Please try again later.'
            ], 500);
        }
    }

    public function destroy(Request $request, Project $project, Group $group): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            if ($group->project_id !== $project->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group does not belong to this project.'
                ], 404);
            }

            if (!$project->isOwner($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the project owner can delete groups.'
                ], 403);
            }

            DB::beginTransaction();

            $group->members()->detach();

            $group->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Group deleted successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete group: ' . $e->getMessage(), [
                'group_id' => $group->id,
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group. Please try again later.'
            ], 500);
        }
    }
    public function myManagedGroups(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $groups = Group::with(['project', 'manager', 'creator', 'members'])
            ->where('manager_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => GroupResource::collection($groups),
            'total' => $groups->count(),
        ]);
    }

    /**
     * Get all groups where the authenticated user is a member.
     */
    public function myGroups(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $groups = Group::with([
                'project',
                'manager',
                'creator',
                'members',
                'groupTasks' => function ($query) {
                    $query->whereNull('parent_task_id')->limit(5);
                }
            ])
                ->whereHas('members', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => GroupResource::collection($groups),
                'total' => $groups->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch my groups: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load groups. Please try again later.'
            ], 500);
        }
    }
}
