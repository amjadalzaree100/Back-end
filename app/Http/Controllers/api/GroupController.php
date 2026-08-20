<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\Project;
use App\Models\Task;
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
            $isOwner = $project->isOwner($userId);
            $isManager = $project->isManager($userId);

            // Determine manager_id based on role
            if ($isOwner) {
                // Owner can optionally provide a manager_id
                $managerId = $request->manager_id; // can be null
            } else {
                // Project manager can provide a manager_id, otherwise becomes the group manager
                $managerId = $request->manager_id ?: $userId;
            }

            // If manager_id is provided, validate it
            if ($managerId) {
                if (!$project->hasUser($managerId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected manager must be a member of this project.'
                    ], 422);
                }

                $managerRole = $project->getUserRole($managerId);
                if ($managerRole !== 'manager') {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected manager must have the manager role in this project.'
                    ], 422);
                }

                if ($project->isOwner($managerId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The project owner cannot be a group manager.'
                    ], 422);
                }
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
                'manager_id' => $managerId,
                'created_by' => $userId,
                'is_active' => true,
            ]);

            // Add manager as member if manager_id is provided
            if ($managerId) {
                $group->addMember($managerId, $userId);
            }

            // Ensure a non-owner creator who assigned another manager stays a member
            if (!$isOwner && $managerId !== $userId) {
                $group->addMember($userId, $userId);
            }

            // Add additional members
            if (!empty($memberIds)) {
                foreach (array_unique($memberIds) as $memberId) {
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

            $isOwner = $project->isOwner($userId);
            $isGroupManager = $group->isManager($userId);

            if (!$isOwner && !$isGroupManager) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this group.'
                ], 403);
            }

            // Group manager can only deactivate the group (set is_active = false)
            if ($isGroupManager && !$isOwner) {
                $group->update(['is_active' => false]);

                return response()->json([
                    'success' => true,
                    'message' => 'Group has been deactivated.'
                ]);
            }

            // Owner can delete, but only if group has no members and no related tasks
            $hasMembers = $group->members()->exists();
            $hasRelatedTasks = Task::where('assigned_group_id', $group->id)->exists();

            if ($hasMembers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete group with members. Please expel all members first using the expel-all-members endpoint.'
                ], 422);
            }

            if ($hasRelatedTasks) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete group with related tasks. Please detach all tasks first using the detach-tasks endpoint.'
                ], 422);
            }

            DB::beginTransaction();

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

    public function expelAllMembers(Request $request, Project $project, Group $group): JsonResponse
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
                    'message' => 'Only the project owner can expel all members.'
                ], 403);
            }

            if (!$group->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify an inactive group.'
                ], 422);
            }

            DB::beginTransaction();

            // Set manager_id to null if there's a manager
            if ($group->manager_id) {
                $group->update(['manager_id' => null]);
            }

            // Detach all members
            $group->members()->detach();

            DB::commit();

            $group->load(['manager', 'members']);

            return response()->json([
                'success' => true,
                'message' => 'All members have been expelled from the group.',
                'data' => new GroupResource($group)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to expel all members: ' . $e->getMessage(), [
                'group_id' => $group->id,
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to expel members. Please try again later.'
            ], 500);
        }
    }

    public function detachTasks(Request $request, Project $project, Group $group): JsonResponse
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
                    'message' => 'Only the project owner or group manager can detach tasks.'
                ], 403);
            }

            if (!$group->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify an inactive group.'
                ], 422);
            }

            $taskIds = $request->input('task_ids');

            $query = Task::where('project_id', $project->id)
                ->where('assigned_group_id', $group->id)
                ->whereNull('parent_task_id');

            if (!empty($taskIds)) {
                $request->validate([
                    'task_ids' => 'required|array',
                    'task_ids.*' => 'integer',
                ]);

                $query->whereIn('id', $taskIds);
            }

            $tasks = $query->get();

            if ($tasks->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tasks found assigned to this group matching the provided IDs.'
                ], 404);
            }

            DB::beginTransaction();

            $detachedTaskIds = $tasks->pluck('id')->toArray();

            // Detach the selected tasks and clear their assignment
            Task::whereIn('id', $detachedTaskIds)
                ->update(['assigned_group_id' => null, 'assigned_to' => null]);

            // Cascade to subtasks (children via parent_task_id)
            $subtaskCount = Task::where('project_id', $project->id)
                ->whereIn('parent_task_id', $detachedTaskIds)
                ->update(['assigned_group_id' => null, 'assigned_to' => null]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tasks have been detached from the group.',
                'data' => [
                    'tasks_detached' => $tasks->count(),
                    'subtasks_detached' => $subtaskCount,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to detach tasks: ' . $e->getMessage(), [
                'group_id' => $group->id,
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to detach tasks. Please try again later.'
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
            $projectId = $request->input('project_id');

            $query = Group::with(['project', 'manager', 'creator', 'members'])
                ->withCount('groupTasks')
                ->whereHas('members', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });

            // Filter by project if provided
            if ($projectId) {
                $project = Project::find($projectId);

                if (!$project) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Project not found'
                    ], 404);
                }

                // Check user has access to this project
                if (!$project->isOwner($userId) && !$project->hasUser($userId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this project'
                    ], 403);
                }

                $query->where('project_id', $projectId);
            }

            $groups = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => GroupResource::collection($groups),
                'total' => $groups->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch my groups: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'project_id' => $request->input('project_id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load groups. Please try again later.'
            ], 500);
        }
    }
}
