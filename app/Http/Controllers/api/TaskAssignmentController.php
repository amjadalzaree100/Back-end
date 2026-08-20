<?php
// app/Http/Controllers/Api/TaskAssignmentController.php

namespace App\Http\Controllers\api;

use App\Events\TaskNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\AssignUsersRequest;
use App\Http\Resources\TaskResource;
use App\Models\Group;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class TaskAssignmentController extends Controller
{
    public function index(Project $project, Task $task): JsonResponse
    {
        $this->checkProjectAccess($project);

        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'primary_assignee' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->name,
                    'email' => $task->assignee->email,
                ] : null,
                'additional_assignees' => [],
                'total' => $task->assigned_to ? 1 : 0,
            ],
        ]);
    }

    public function assign(AssignUsersRequest $request, Project $project, Task $task): JsonResponse
    {
        $userId = $request->user()->id;
        $isOwner = $project->isOwner($userId);
        $isProjectManager = $project->isManager($userId);

        if (!$isOwner && !$isProjectManager) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to assign tasks.',
            ], 403);
        }

        if ($task->is_archived) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot assign users to an archived task.',
            ], 403);
        }

        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        if (!$task->canBeAssigned()) {
            return response()->json([
                'success' => false,
                'message' => 'This task cannot be assigned.',
            ], 422);
        }

        // Find groups managed by this user in this project
        $managedGroupIds = Group::where('project_id', $project->id)
            ->where('manager_id', $userId)
            ->pluck('id')
            ->toArray();

        // Determine if the task is assigned to one of the caller's groups
        $taskBelongsToMyGroup = $task->assigned_group_id && in_array($task->assigned_group_id, $managedGroupIds);

        // If the task is assigned to a group that the caller does NOT manage, and caller is not owner
        if ($task->assigned_group_id && !$taskBelongsToMyGroup && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'You can only assign tasks that belong to your group.',
            ], 403);
        }

        // If the task is assigned to a group, check if the group is active
        if ($task->assigned_group_id) {
            $taskGroup = Group::find($task->assigned_group_id);
            if ($taskGroup && !$taskGroup->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign tasks in an inactive group.',
                ], 422);
            }
        }

        // Validate the provided status belongs to this project
        $status = TaskStatus::where('id', $request->status_id)
            ->where('project_id', $project->id)
            ->first();

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Selected status does not belong to this project.',
            ], 422);
        }

        $assigneeId = $request->user_ids[0];

        // Validate the assignee
        if (!$isOwner && $taskBelongsToMyGroup) {
            // Group manager assigning to a group task: assignee must be a group member
            $assignedGroup = Group::find($task->assigned_group_id);
            if (!$assignedGroup) {
                return response()->json([
                    'success' => false,
                    'message' => 'The assigned group no longer exists.',
                ], 404);
            }
            if (!$assignedGroup->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign tasks in an inactive group.',
                ], 422);
            }
            if (!$assignedGroup->isMember($assigneeId)) {
                return response()->json([
                    'success' => false,
                    'message' => "User ID {$assigneeId} is not a member of this group.",
                ], 422);
            }
        } else {
            // Owner or project manager assigning: assignee must be a project member
            $projectUsers = $project->users()->pluck('users.id')->toArray();
            $projectUsers[] = $project->created_by;

            if (!in_array($assigneeId, $projectUsers)) {
                return response()->json([
                    'success' => false,
                    'message' => "User ID {$assigneeId} is not a member of this project",
                ], 422);
            }
        }

        $oldStatusId = $task->status_id;

        try {
            DB::beginTransaction();

            $task->assigned_to = $assigneeId;
            $task->status_id = $request->status_id;
            $task->save();

            if ($oldStatusId !== (int) $request->status_id) {
                DB::table('task_status_histories')->insert([
                    'task_id'        => $task->id,
                    'from_status_id' => $oldStatusId,
                    'to_status_id'   => $request->status_id,
                    'changed_by'     => $userId,
                    'changed_at'     => now(),
                ]);
            }

            TaskAssignmentHistory::create([
                'task_id' => $task->id,
                'user_id' => $assigneeId,
                'assigned_by' => $userId,
                'action' => 'assigned',
                'assigned_at' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign user',
                'error' => $e->getMessage(),
            ], 500);
        }

        TaskNotificationEvent::dispatch(
            userIds: [$assigneeId],
            scenario: 'assigned',
            task: $task,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'User assigned to task successfully',
            'data' => [
                'assigned_to' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->name,
                ] : null,
            ],
        ], 201);
    }

    public function unassign(Project $project, Task $task, int $userId, Request $request): JsonResponse
    {
        $this->checkProjectManager($project);

        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        if ($task->assigned_to !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'User is not assigned to this task',
            ], 404);
        }
        $currentUserId = $request->user()->id;

        $oldStatusId = $task->status_id;

        try {
            DB::beginTransaction();

            $task->assigned_to = null;
            $task->status_id = null;
            $task->save();

            if (!is_null($oldStatusId)) {
                DB::table('task_status_histories')->insert([
                    'task_id'        => $task->id,
                    'from_status_id' => $oldStatusId,
                    'to_status_id'   => null,
                    'changed_by'     => $currentUserId,
                    'changed_at'     => now(),
                ]);
            }

            TaskAssignmentHistory::create([
                'task_id' => $task->id,
                'user_id' => $userId,
                'assigned_by' => $currentUserId,
                'action' => 'unassigned',
                'assigned_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User unassigned from task successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function myAssignedTasks(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $tasks = Task::with(['project', 'status', 'creator', 'assignee'])
            ->where('assigned_to', $userId)
            ->orderBy('due_date')
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => TaskResource::collection($tasks),
            'total' => $tasks->count(),
        ]);
    }

    private function checkProjectAccess(Project $project): void
    {
        $userId = request()->user()->id;
        if (!$project->isOwner($userId) && !$project->hasUser($userId)) {
            abort(403, 'You do not have access to this project');
        }
    }

    private function checkProjectManager(Project $project): void
    {
        $userId = request()->user()->id;
        if (!$project->isOwner($userId) && !$project->isManager($userId)) {
            abort(403, 'You do not have permission to manage task assignments');
        }
    }
}