<?php
// app/Http/Controllers/Api/TaskAssignmentController.php

namespace App\Http\Controllers\api;

use App\Events\TaskNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\AssignUsersRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
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

        $assignments = $task->assignees()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'primary_assignee' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->name,
                    'email' => $task->assignee->email,
                ] : null,
                'additional_assignees' => $assignments->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
                'total' => $assignments->count() + ($task->assigned_to ? 1 : 0),
            ],
        ]);
    }

    public function assign(AssignUsersRequest $request, Project $project, Task $task): JsonResponse
    {
        $this->checkProjectManager($project);
        $userId = $request->user()->id;

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

        $projectUsers = $project->users()->pluck('users.id')->toArray();
        $projectUsers[] = $project->created_by;

        foreach ($request->user_ids as $userId) {
            if (!in_array($userId, $projectUsers)) {
                return response()->json([
                    'success' => false,
                    'message' => "User ID {$userId} is not a member of this project",
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $task->assignees()->syncWithoutDetaching($request->user_ids);
            foreach ($request->user_ids as $assignedUserId) {
                DB::table('task_assignment_histories')->insert([
                    'task_id' => $task->id,
                    'user_id' => $assignedUserId,
                    'assigned_by' => $userId,
                    'action' => 'assigned',
                    'assigned_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign users',
                'error' => $e->getMessage(),
            ], 500);
        }

        TaskNotificationEvent::dispatch(
            userIds: $request->user_ids,
            scenario: 'assigned',
            task: $task,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Users assigned to task successfully',
            'data' => [
                'assigned_users' => $task->assignees()->get()->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                ]),
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

        if ($task->assigned_to === $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot unassign the primary assignee. Update the task instead.',
            ], 422);
        }

        if (!$task->assignees()->where('user_id', $userId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not assigned to this task',
            ], 404);
        }
        $currentUserId = $request->user()->id;

        try {
            DB::beginTransaction();

            $task->assignees()->detach($userId);

            DB::table('task_assignment_histories')->insert([
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

        $tasks = Task::with(['project', 'status', 'creator', 'assignee', 'assignees'])
            ->where('assigned_to', $userId)
            ->orWhereHas('assignees', fn($q) => $q->where('user_id', $userId))
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
