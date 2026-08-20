<?php

namespace App\Http\Controllers\api;

use App\Events\TaskNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ReorderTasksRequest;
use App\Http\Requests\Task\StoreGroupTaskRequest;
use App\Http\Requests\Task\StoreSubTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Group;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ProjectStatsResource;
use App\Services\TaskTransferService;
use Illuminate\Validation\ValidationException;



class TaskController extends Controller
{
    /**
     * Create a new project task.
     * - If allow_subtasks = true → task becomes a parent task (cannot be assigned).
     * - Otherwise, normal assignable task.
     */
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $this->checkProjectManager($project);

        $allowSubtasks = $request->input('allow_subtasks', false);

        $maxPosition = Task::where('project_id', $project->id)
            ->whereNull('status_id')
            ->max('position') ?? -1;

        try {
            DB::beginTransaction();

            $task = Task::create([
                'project_id' => $project->id,
                'status_id' => null,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority ?? 'medium',
                'due_date' => $request->due_date,
                'created_by' => $request->user()->id,
                'assigned_to' => $request->assigned_to,
                'position' => $maxPosition + 1,
                'allow_subtasks' => $allowSubtasks,
                'can_be_assigned' => true,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task creation failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task. Please try again later.',
            ], 500);
        }

        $task->load(['status', 'creator', 'assignee']);

        if ($task->assigned_to) {
            TaskNotificationEvent::dispatch(
                userIds: [$task->assigned_to],
                scenario: 'assigned',
                task: $task,
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => new TaskResource($task),
        ], 201);
    }

    /**
     * Create a task assigned to an entire group.
     * (type: groupTask)
     */
    public function storeGroupTask(StoreGroupTaskRequest $request, Project $project): JsonResponse
    {
        $group = Group::where('id', $request->assigned_group_id)
            ->where('project_id', $project->id)
            ->first();

        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found or does not belong to this project',
            ], 404);
        }

        $allowSubtasks = $request->input('allow_subtasks', false);

        $maxPosition = Task::where('project_id', $project->id)
            ->max('position') ?? -1;

        try {
            DB::beginTransaction();

            $task = Task::create([
                'project_id' => $project->id,
                'assigned_group_id' => $group->id,
                'status_id' => null,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority ?? 'medium',
                'due_date' => $request->due_date,
                'created_by' => $request->user()->id,
                'assigned_to' => $request->assigned_to,
                'position' => $maxPosition + 1,
                'allow_subtasks' => $allowSubtasks,
                'can_be_assigned' => true,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Group task creation failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'assigned_group_id' => $group->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create group task. Please try again later.',
            ], 500);
        }

        $task->load(['status', 'creator', 'assignedGroup', 'assignee']);

        $userIds = [];
        if ($task->assigned_to) {
            $userIds[] = $task->assigned_to;
        }

        if ($task->assignedGroup) {
            $groupMemberIds = $task->assignedGroup->members()->pluck('users.id')->toArray();
            $userIds = array_merge($userIds, $groupMemberIds);
        }

        if (!empty($userIds)) {
            TaskNotificationEvent::dispatch(
                userIds: array_unique($userIds),
                scenario: 'assigned',
                task: $task,
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Group task created successfully',
            'data' => new TaskResource($task),
        ], 201);
    }

    /**
     * Create a subtask under a parent task (either project parent or group parent).
     * Subtasks are always assignable and require at least one assignee.
     */
    public function storeSubTask(StoreSubTaskRequest $request, Project $project, Group $group, Task $parentTask): JsonResponse
    {
        $userId = $request->user()->id;
        $this->checkProjectManager($project);

        // Validation: parent task must belong to the same project and group
        if ($parentTask->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Parent task does not belong to this project',
            ], 404);
        }

        if ($parentTask->assigned_group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Parent task does not belong to this group',
            ], 404);
        }

        // Check that parent task allows subtasks
        if (!$parentTask->allow_subtasks) {
            return response()->json([
                'success' => false,
                'message' => 'The parent task does not allow subtasks.',
            ], 422);
        }

        // If the parent task is assigned to a group, the subtask assignee must be a member of that group
        if ($parentTask->assigned_group_id && $request->assigned_to) {
            $assignedGroup = Group::find($parentTask->assigned_group_id);
            if ($assignedGroup && !$assignedGroup->isMember($request->assigned_to)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The assignee must be a member of the group.',
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $assignedTo = is_array($request->assigned_to) ? $request->assigned_to[0] : $request->assigned_to;

            $subTask = Task::create([
                'project_id' => $project->id,
                'assigned_group_id' => $parentTask->assigned_group_id,
                'parent_task_id' => $parentTask->id,
                'status_id' => null,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority ?? 'medium',
                'due_date' => $request->due_date,
                'created_by' => $userId,
                'assigned_to' => $assignedTo,
                'allow_subtasks' => false,
                'can_be_assigned' => true,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subtask creation failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'parent_task_id' => $parentTask->id,
                'user_id' => $userId,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subtask. Please try again later.',
            ], 500);
        }

        $subTask->load(['status', 'creator', 'assignee']);

        $subTaskAssigneeIds = $subTask->assigned_to ? [$subTask->assigned_to] : [];
        if (!empty($subTaskAssigneeIds)) {
            TaskNotificationEvent::dispatch(
                userIds: array_unique($subTaskAssigneeIds),
                scenario: 'assigned',
                task: $subTask,
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Subtask created and assigned successfully',
            'data' => new TaskResource($subTask),
        ], 201);
    }
    public function show(Request $request, Project $project, Task $task): JsonResponse
    {
        try {
            // Allow access only to project members/owners
            if (!$project->hasUser($request->user()->id) && !$project->isOwner($request->user()->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this task'
                ], 403);
            }


            if ($task->project_id !== $project->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task does not belong to this project',
                ], 404);
            }

            // Load all necessary relations for TaskResource
            $task->load([
                'status',
                'creator',
                'assignee',
                'dependencies',
                'comments.user',
                'subTasks.status',
                'subTasks.assignee',
                'subTasks.assignedGroup',
                'assignedGroup',
                'parentTask',
            ]);

            return response()->json([
                'success' => true,
                'data' => new TaskResource($task),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching task details failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'project_id' => $project->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load task details. Please try again later.',
            ], 500);
        }
    }


    /**
     * Update a task.
     * Only the project owner or task creator can update basic fields.
     * Assignment updates are allowed only for assignable tasks and by the project owner.
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task): JsonResponse
    {

        if ($task->is_archived) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update an archived task.',
            ], 403);
        }

        // 1. Verify the task belongs to the project
        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        $userId = $request->user()->id;
        $isOwner = $project->isOwner($userId);
        $isTaskCreator = $task->created_by === $userId;

        if (!$isOwner && !$isTaskCreator) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this task.',
            ], 403);
        }

        // 2. If the task belongs to a group and its assignee changes, the new assignee must be a member of the group
        if ($task->assigned_group_id && $request->has('assigned_to') && $request->assigned_to) {
            $assignedGroup = Group::find($task->assigned_group_id);
            if ($assignedGroup && !$assignedGroup->isMember($request->assigned_to)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The assignee must be a member of the assigned group.',
                ], 422);
            }
        }

        // 4. Allowed fields for update (exclude allow_subtasks, can_be_assigned)
        $allowedFields = ['title', 'description', 'priority', 'due_date', 'assigned_to', 'position'];
        if ($isOwner) {
            $updateData = $request->only($allowedFields);
        } else {
            // Task creator cannot update assignment or position
            $updateData = $request->only(['title', 'description', 'priority', 'due_date']);
        }

        // 5. Prevent direct position update (use reorder / updateStatus endpoints instead)
        if (isset($updateData['position'])) {
            return response()->json([
                'success' => false,
                'message' => 'Position cannot be updated directly. Use reorder endpoint.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $task->update($updateData);

            // 6. When the assigned_group_id changes (task moved to a different group),
            //    cascade to all subtasks and clear assignments (parent and subtasks)
            if ($isOwner && $request->has('assigned_group_id')) {
                // Prevent subtasks from being moved independently
                if ($task->parent_task_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Subtasks cannot be moved to a different group independently. Update the parent task instead.',
                    ], 422);
                }

                $newGroupId = $request->assigned_group_id;
                $task->assigned_group_id = $newGroupId;

                // Cascade to subtasks
                Task::where('parent_task_id', $task->id)->update([
                    'assigned_group_id' => $newGroupId,
                    'assigned_to' => null,
                ]);

                // Clear parent assignment
                $task->assigned_to = null;
                $task->save();
            }

            DB::commit();

            $task->load(['status', 'creator', 'assignee']);

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => new TaskResource($task),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task update failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task. Please try again later.',
            ], 500);
        }
    }


    /**
     * Change task status (move to another column) and reorder automatically.
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Project $project, Task $task): JsonResponse
    {
        // 1. Verify task belongs to the project
        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        $userId = $request->user()->id;
        $userRole = $project->getUserRole($userId);
        $isOwner = $project->isOwner($userId);
        $isManager = $userRole === 'manager';
        $isUser = $userRole === 'user';
        $isTaskAssignee = $task->assigned_to === $userId;

        if (!($isOwner || $isManager || ($isUser && $isTaskAssignee))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to change task status',
            ], 403);
        }

        $oldStatusId = $task->status_id;
        $newStatusId = $request->status_id;

        // 2. Ensure the new status belongs to the same project
        if (!$project->taskStatuses()->where('id', $newStatusId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'The selected status does not belong to this project',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Shift positions in old status (remove gap)
            Task::where('project_id', $project->id)
                ->where('status_id', $oldStatusId)
                ->where('position', '>', $task->position)
                ->decrement('position');

            // Calculate new position (end of new status by default)
            $newPosition = $request->position ??
                Task::where('project_id', $project->id)
                ->where('status_id', $newStatusId)
                ->max('position') + 1;

            // Shift positions in new status (make room)
            Task::where('project_id', $project->id)
                ->where('status_id', $newStatusId)
                ->where('position', '>=', $newPosition)
                ->increment('position');

            // Update task
            $task->update([
                'status_id' => $newStatusId,
                'position' => $newPosition,
            ]);

            DB::table('task_status_histories')->insert([
                'task_id' => $task->id,
                'from_status_id' => $oldStatusId,
                'to_status_id' => $newStatusId,
                'changed_by' => $userId,
                'changed_at' => now(),
            ]);

            DB::commit();

            $task->load(['status', 'creator', 'assignee']);

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully',
                'data' => new TaskResource($task),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task status update failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'project_id' => $project->id,
                'user_id' => $userId,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task status. Please try again later.',
            ], 500);
        }
    }

    /**
     * Reorder multiple tasks (change position and/or status).
     * Only project owner or manager can perform this action.
     */
    public function reorder(ReorderTasksRequest $request, Project $project): JsonResponse
    {
        $this->checkProjectManager($project);

        $tasksData = $request->input('tasks');

        // 1. Verify all tasks belong to the project
        $taskIds = array_column($tasksData, 'id');
        $validTaskIds = Task::where('project_id', $project->id)->whereIn('id', $taskIds)->pluck('id')->toArray();
        $invalidIds = array_diff($taskIds, $validTaskIds);
        if (!empty($invalidIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some tasks do not belong to this project: ' . implode(', ', $invalidIds),
            ], 422);
        }

        // 2. Verify all status_ids belong to the project
        $statusIds = array_unique(array_column($tasksData, 'status_id'));
        $validStatusIds = $project->taskStatuses()->whereIn('id', $statusIds)->pluck('id')->toArray();
        $invalidStatusIds = array_diff($statusIds, $validStatusIds);
        if (!empty($invalidStatusIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some statuses do not belong to this project: ' . implode(', ', $invalidStatusIds),
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($tasksData as $taskData) {
                Task::where('id', $taskData['id'])
                    ->where('project_id', $project->id)
                    ->update([
                        'position' => $taskData['position'],
                        'status_id' => $taskData['status_id'],
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tasks reordered successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task reorder failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => request()->user()->id,
                'tasks_data' => $tasksData,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder tasks. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get full history (status changes + assignment changes) for a task.
     */
    public function getTaskHistory(Request $request, Project $project, Task $task): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            if ($task->project_id !== $project->id) {
                return response()->json(['message' => 'Task does not belong to this project'], 404);
            }

            $statusHistory = $task->statusHistories()
                ->with(['fromStatus', 'toStatus', 'changedBy'])
                ->get();

            $assignmentHistory = $task->assignmentHistories()
                ->with(['user', 'assignedBy'])
                ->get();

            $history = collect()
                ->merge($statusHistory->map(fn($item) => [
                    'type' => 'status_change',
                    'from' => $item->fromStatus?->name,
                    'to' => $item->toStatus?->name,
                    'changed_by' => $item->changedBy?->name,
                    'changed_at' => $item->changed_at,
                ]))
                ->merge($assignmentHistory->map(fn($item) => [
                    'type' => 'assignment_change',
                    'action' => $item->action,
                    'user' => $item->user?->name,
                    'assigned_by' => $item->assignedBy?->name,
                    'changed_at' => $item->assigned_at,
                ]))
                ->sortByDesc('changed_at')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $history,
                'task_id' => $task->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch task history: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load task history.'
            ], 500);
        }
    }











    /**
     * Get all completed tasks of the project.
     */
    public function getCompletedTasks(Project $project, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $tasks = $project->tasks()
                ->whereNotNull('completed_at')
                ->with(['status', 'creator', 'assignee', 'assignedGroup'])
                ->orderBy('completed_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching completed tasks failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load completed tasks. Please try again later.'
            ], 500);
        }
    }
    /**
     * Get all assigned tasks (assigned to any project user) grouped by status for Kanban view.
     */
    public function getAssignedTasksKanban(Project $project, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $statuses = $project->taskStatuses()
                ->orderBy('position')
                ->get();

            $tasks = $project->tasks()
                ->where('is_archived', false)
                ->where(function ($q) {
                    $q->whereNotNull('assigned_to')
                        ->orWhereNotNull('assigned_group_id');
                })
                ->with([
                    'status',
                    'creator',
                    'assignee',
                    'assignedGroup',
                    'subTasks.status',
                    'subTasks.assignee',
                    'subTasks.assignedGroup',
                ])
                ->orderBy('position')
                ->get();

            $grouped = $tasks->groupBy(function ($task) {
                return $task->status_id ?? 'no-status';
            });

            $kanban = $statuses->map(function ($status) use ($grouped) {
                $statusTasks = $grouped->get($status->id, collect());

                return [
                    'status' => [
                        'id' => $status->id,
                        'name' => $status->name,
                        'position' => $status->position,
                    ],
                    'tasks' => TaskResource::collection($statusTasks),
                    'tasks_count' => $statusTasks->count(),
                ];
            });

            if ($grouped->has('no-status')) {
                $noStatusTasks = $grouped->get('no-status');

                $kanban->push([
                    'status' => null,
                    'tasks' => TaskResource::collection($noStatusTasks),
                    'tasks_count' => $noStatusTasks->count(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $kanban->values(),
                'total_tasks' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching assigned tasks kanban failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load assigned tasks. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get all assigned tasks (tasks that have at least one assignee or assigned group).
     */
    public function getAssignedTasks(Project $project, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $tasks = $project->tasks()
                ->where(function ($q) {
                    $q->whereNotNull('assigned_to')
                        ->orWhereNotNull('assigned_group_id');
                })
                ->with(['status', 'creator', 'assignee', 'assignedGroup'])
                ->orderBy('due_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching assigned tasks failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load assigned tasks. Please try again later.'
            ], 500);
        }
    }
    /**
     * Get all unassigned tasks (no assignee, no assignments, no assigned group).
     */
    public function getUnassignedTasks(Project $project, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $tasks = $project->tasks()
                ->whereNull('assigned_to')
                ->whereNull('assigned_group_id')
                ->with(['status', 'creator'])
                ->orderBy('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching unassigned tasks failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load unassigned tasks. Please try again later.'
            ], 500);
        }
    }









    /**
     * Ensure the authenticated user is a member or owner of the project.
     */
    private function checkProjectAccess(Project $project): void
    {
        $userId = request()->user()->id;
        if (!$project->isOwner($userId) && !$project->hasUser($userId)) {
            abort(403, 'You must be a member of this project to access its tasks.');
        }
    }
    private function checkProjectManager(Project $project): void
    {
        $userId = request()->user()->id;
        if (!$project->isOwner($userId) && !$project->isManager($userId)) {
            abort(403, 'You do not have permission to manage tasks');
        }
    }

    private function checkTaskManagePermission(Project $project, User $user): void
    {
        $isOwner = $project->isOwner($user->id);
        if (!$isOwner) {
            abort(403, 'You do not have permission to manage tasks.');
        }
    }
















    /**
     * Soft delete a task.
     * Adjusts positions of remaining tasks in the same status.
     * Prevents deletion if the task has subtasks (parent task).
     */
    public function destroy(Project $project, Task $task): JsonResponse
    {

        if ($task->is_archived) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an archived task.',
            ], 403);
        }
        // Verify task belongs to the project
        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        // Only project owner or manager can delete tasks
        $this->checkProjectManager($project);

        try {
            DB::beginTransaction();

            // Reorder: shift up tasks that were after this one in the same status
            Task::where('project_id', $project->id)
                ->where('status_id', $task->status_id)
                ->where('position', '>', $task->position)
                ->decrement('position');

            // Soft delete the task
            $task->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task deletion failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task. Please try again later.',
            ], 500);
        }
    }

    /**
     * Display soft-deleted tasks (trash) of the project.
     * Only project owner or manager can view trashed tasks.
     */
    public function trashed(Project $project, Request $request): JsonResponse
    {
        try {
            // Only owner or manager can view trash (not regular members)
            $this->checkProjectManager($project);

            $tasks = Task::onlyTrashed()
                ->where('project_id', $project->id)
                ->with(['status', 'creator', 'assignee'])
                ->orderBy('deleted_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching trashed tasks failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load trashed tasks. Please try again later.'
            ], 500);
        }
    }


    /**
     * Restore a soft-deleted task.
     * Only the project owner can perform this action.
     */
    public function restoreTask(Project $project, Task $task, Request $request): JsonResponse
    {
        // 1. Verify task belongs to the project
        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        // 2. Ensure the task is actually soft-deleted
        if (!$task->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Task is not deleted. Nothing to restore.',
            ], 422);
        }

        // 3. Authorization: only project owner
        $this->checkTaskManagePermission($project, $request->user());

        try {
            DB::beginTransaction();

            // Restore the task (also restores assignments, comments, subtasks via model event)
            $task->restore();

            DB::commit();

            // Load necessary relations for response
            $task->load(['status', 'creator', 'assignee']);

            return response()->json([
                'success' => true,
                'message' => 'Task restored successfully.',
                'data' => new TaskResource($task),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task restore failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore task. Please try again later.',
            ], 500);
        }
    }


    /**
     * Permanently delete a soft-deleted task (including all relations).
     * Only the project owner can perform this action.
     */
    public function forceDeleteTask(Project $project, Task $task, Request $request): JsonResponse
    {
        // 1. Verify task belongs to the project
        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        // 2. Ensure the task is soft-deleted
        if (!$task->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Task is not deleted. Use delete endpoint first.',
            ], 422);
        }

        // 3. Authorization: only project owner
        $this->checkTaskManagePermission($project, $request->user());

        try {
            DB::beginTransaction();

            // Force delete the task (model booted will handle assignments, comments, subtasks, dependencies)
            $task->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task permanently deleted.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Force delete task failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete task. Please try again later.',
            ], 500);
        }
    }

    /**
     * Permanently delete all soft-deleted tasks in the project.
     * Only project owner or manager can perform this action
     */
    public function emptyTrash(Project $project, Request $request): JsonResponse
    {
        // 1. Authorization: owner or manager
        $this->checkProjectManager($project);

        // 2. Get all trashed tasks of this project (ensure they are Task models)
        $trashedTasks = Task::onlyTrashed()
            ->where('project_id', $project->id)
            ->get();

        if ($trashedTasks->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Trash is already empty.',
                'deleted_count' => 0,
            ]);
        }

        $deletedCount = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($trashedTasks as $task) {
                // Safety check: ensure it's a Task model
                if (!$task instanceof Task) {
                    $errors[] = [
                        'task_id' => $task->id ?? 'unknown',
                        'message' => 'Invalid task object, skipping.',
                    ];
                    continue;
                }

                try {
                    $task->forceDelete(); // triggers model events (cleans up assignments, comments, subtasks)
                    $deletedCount++;
                } catch (\Exception $e) {
                    // If any deletion fails, we roll back everything (atomicity)
                    throw new \Exception("Failed to delete task ID {$task->id}: " . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $deletedCount === 1
                    ? "{$deletedCount} task permanently deleted from trash."
                    : "{$deletedCount} tasks permanently deleted from trash.",
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Empty trash failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to empty trash. Please try again later.',
                'errors' => $errors,
            ], 500);
        }
    }



    public function myPendingTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $sortBy = $request->input('sort_by', 'due_date');
        $sortDir = $request->input('sort_direction', 'asc');

        $allowedSorts = ['due_date', 'created_at', 'priority', 'title'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'due_date';
        }

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }

        $tasks = Task::with(['project', 'status', 'creator', 'assignee'])
            ->whereNull('completed_at')
            ->where('assigned_to', $userId)
            ->orderBy($sortBy, $sortDir)
            ->get();

        return response()->json([
            'success' => true,
            'data' => TaskResource::collection($tasks),
            'total' => $tasks->count(),
        ]);
    }


    // Get project statistics for dashboard.

    public function getProjectStats(Request $request, Project $project): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            if (!$project->isOwner($userId) && !$project->hasUser($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project statistics.'
                ], 403);
            }

            $project->load([
                'users',
                'tasks',
                'taskStatuses',
                'groups',
            ]);

            return response()->json([
                'success' => true,
                'data' => new ProjectStatsResource($project)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch project statistics: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load project statistics. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get all completed group tasks for a specific group.
     * (tasks assigned to the group: assigned_group_id = group.id)
     */
    public function getGroupCompletedTasks(Project $project, Group $group, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $tasks = Task::where('project_id', $project->id)
                ->where('assigned_group_id', $group->id)
                ->whereNotNull('completed_at')
                ->with(['status', 'creator', 'assignee', 'assignedGroup'])
                ->orderBy('completed_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch group completed tasks: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'group_id' => $group->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load group completed tasks. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get all archived group tasks for a specific group.
     * (tasks assigned to the group: assigned_group_id = group.id)
     */
    public function getGroupArchivedTasks(Project $project, Group $group, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $tasks = Task::where('project_id', $project->id)
                ->where('assigned_group_id', $group->id)
                ->where('is_archived', true)
                ->with(['status', 'creator', 'assignee', 'assignedGroup'])
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch group archived tasks: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'group_id' => $group->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load group archived tasks. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get group tasks that are assigned to at least one member of the group.
     * (tasks assigned to the group: assigned_group_id = group.id)
     */
    public function getGroupAssignedTasks(Project $project, Group $group, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $groupMemberIds = $group->members()->pluck('users.id')->toArray();

            $tasks = Task::where('project_id', $project->id)
                ->where('assigned_group_id', $group->id)
                ->whereIn('assigned_to', $groupMemberIds)
                ->with(['status', 'creator', 'assignee', 'assignedGroup'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch group assigned tasks: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'group_id' => $group->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load group assigned tasks. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get group tasks that are NOT assigned to any member of the group.
     * (tasks assigned to the group: assigned_group_id = group.id)
     */
    public function getGroupUnassignedTasks(Project $project, Group $group, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $groupMemberIds = $group->members()->pluck('users.id')->toArray();

            $tasks = Task::where('project_id', $project->id)
                ->where('assigned_group_id', $group->id)
                ->where(function ($q) use ($groupMemberIds) {
                    $q->whereNull('assigned_to')
                        ->orWhereNotIn('assigned_to', $groupMemberIds);
                })
                ->with(['status', 'creator', 'assignee', 'assignedGroup'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch group unassigned tasks: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'group_id' => $group->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load group unassigned tasks. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get all group tasks for a specific project.
     */
    public function getGroupTasks(Project $project, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $search = $request->input('search');
            $statusId = $request->input('status_id');
            $priority = $request->input('priority');
            $sortBy = $request->input('sort_by', 'position');
            $sortDirection = $request->input('sort_direction', 'asc');

            // Allowed sort columns
            $allowedSorts = ['id', 'title', 'priority', 'due_date', 'position', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'position';
            }
            if (!in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'asc';
            }

            $tasks = Task::where('project_id', $project->id)
                ->whereNotNull('assigned_group_id')
                ->with([
                    'status',
                    'creator',
                    'assignedGroup',
                    'assignee',
                    'subTasks.status',
                    'subTasks.assignee',
                    'subTasks.assignedGroup',
                ])
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%{$search}%")
                            ->orWhere('description', 'LIKE', "%{$search}%");
                    });
                })
                ->when($statusId, fn($q) => $q->where('status_id', $statusId))
                ->when($priority, fn($q) => $q->where('priority', $priority))
                ->orderBy($sortBy, $sortDirection)
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
                'filters' => [
                    'search' => $search,
                    'status_id' => $statusId,
                    'priority' => $priority,
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch group tasks: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load group tasks. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get tasks related to a specific group that have no status (not yet placed on the board).
     * Includes group tasks (assigned_group_id).
     * Excludes subtasks (they are managed under their parent).
     */
    public function getGroupBoard(Project $project, Group $group, Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            if ($group->project_id !== $project->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group does not belong to this project.',
                ], 404);
            }

            // Check access: owner, group manager, or group member
            $isOwner = $project->isOwner($userId);
            $isGroupManager = $group->isManager($userId);
            $isGroupMember = $group->isMember($userId);

            if (!$isOwner && !$isGroupManager && !$isGroupMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this group board.',
                ], 403);
            }

            $tasks = Task::where('project_id', $project->id)
                ->whereNull('status_id')
                ->whereNull('parent_task_id')
                ->where('assigned_group_id', $group->id)
                ->where('is_archived', false)
                ->with([
                    'creator',
                    'assignee',
                    'assignedGroup',
                    'subTasks.status',
                    'subTasks.assignee',
                ])
                ->orderBy('position')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch group board: ' . $e->getMessage(), [
                'assigned_group_id' => $group->id,
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load group board. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get all tasks related to a specific group, grouped by status for Kanban view.
     * Includes group tasks (assigned_group_id).
     * Excludes subtasks (they are managed under their parent).
     */
    public function getGroupKanban(Project $project, Group $group, Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            if ($group->project_id !== $project->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group does not belong to this project.',
                ], 404);
            }

            // Check access: owner, group manager, or group member
            $isOwner = $project->isOwner($userId);
            $isGroupManager = $group->isManager($userId);
            $isGroupMember = $group->isMember($userId);

            if (!$isOwner && !$isGroupManager && !$isGroupMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this group kanban.',
                ], 403);
            }

            $statuses = $project->taskStatuses()
                ->orderBy('position')
                ->get();

            $groupMemberIds = $group->members()->pluck('users.id')->toArray();

            $tasks = Task::where('project_id', $project->id)
                ->whereNull('parent_task_id')
                ->where('assigned_group_id', $group->id)
                ->where('is_archived', false)
                ->with([
                    'status',
                    'creator',
                    'assignee',
                    'assignedGroup',
                    'subTasks.status',
                    'subTasks.assignee',
                    'subTasks.assignedGroup',
                ])
                ->orderBy('position')
                ->get();

            $grouped = $tasks->groupBy(function ($task) {
                return $task->status_id ?? 'no-status';
            });

            $kanban = $statuses->map(function ($status) use ($grouped) {
                $statusTasks = $grouped->get($status->id, collect());

                return [
                    'status' => [
                        'id' => $status->id,
                        'name' => $status->name,
                        'position' => $status->position,
                    ],
                    'tasks' => TaskResource::collection($statusTasks),
                    'tasks_count' => $statusTasks->count(),
                ];
            });

            // Add no-status column if there are tasks without a status
            if ($grouped->has('no-status')) {
                $noStatusTasks = $grouped->get('no-status');

                $kanban->push([
                    'status' => null,
                    'tasks' => TaskResource::collection($noStatusTasks),
                    'tasks_count' => $noStatusTasks->count(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $kanban->values(),
                'total_tasks' => $tasks->count(),
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch group kanban: ' . $e->getMessage(), [
                'assigned_group_id' => $group->id,
                'project_id' => $project->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load group kanban. Please try again later.',
            ], 500);
        }
    }


    protected TaskTransferService $taskTransferService;

    public function __construct(TaskTransferService $taskTransferService)
    {
        $this->taskTransferService = $taskTransferService;
    }

    public function transfer(Request $request, Task $task)
    {
        try {
            $request->validate([
                'target_project_id' => 'required|exists:projects,id',
                'note' => 'nullable|string|max:500',
            ]);

            // Check if user is owner of the source project
            if ($task->project->created_by !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the project owner can transfer tasks.',
                ], 403);
            }

            $newTask = $this->taskTransferService->transfer(
                $task->id,
                $request->target_project_id,
                $request->user()->id,
                $request->note
            );

            return response()->json([
                'success' => true,
                'message' => 'Task transferred successfully.',
                'data' => new TaskResource($newTask),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to transfer task: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer task. Please try again later.',
            ], 500);
        }
    }

    public function transferHistory(Task $task, Request $request)
    {
        try {
            $history = $this->taskTransferService->getTransferHistory($task->id);

            return response()->json([
                'success' => true,
                'data' => $history,
                'task_id' => $task->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch transfer history: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transfer history. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get all archived tasks for a specific project.
     * Archived tasks are read-only and hidden from the main Kanban board.
     */
    public function archivedTasks(Project $project, Request $request): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $search = $request->input('search');
            $sortBy = $request->input('sort_by', 'updated_at');
            $sortDirection = $request->input('sort_direction', 'desc');

            $allowedSorts = ['id', 'title', 'due_date', 'created_at', 'updated_at', 'transferred_from_task_id'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'updated_at';
            }
            if (!in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }

            $tasks = $project->tasks()
                ->where('is_archived', true)
                ->with([
                    'status',
                    'creator',
                    'assignee',
                    'transferredFrom',
                    'transferredTo',
                ])
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%{$search}%")
                            ->orWhere('description', 'LIKE', "%{$search}%");
                    });
                })
                ->orderBy($sortBy, $sortDirection)
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskResource::collection($tasks),
                'total' => $tasks->count(),
                'message' => 'Archived tasks are read-only and cannot be modified.',
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching archived tasks failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load archived tasks. Please try again later.'
            ], 500);
        }
    }

    public function myKanbanTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $projectId = $request->input('project_id');

        if (!$projectId) {
            return response()->json([
                'success' => false,
                'message' => 'project_id parameter is required'
            ], 422);
        }

        $project = Project::find($projectId);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found'
            ], 404);
        }

        if (!$project->isOwner($userId) && !$project->hasUser($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this project'
            ], 403);
        }

        $tasks = Task::with([
            'status',
            'project',
            'assignee',
            'parentTask',
            'subTasks' => function ($query) {
                $query->with(['status', 'assignee'])->orderBy('position');
            }
        ])
            ->where('project_id', $projectId)
            ->where('assigned_to', $userId)
            ->where('is_archived', false)
            ->get();

        $statuses = $project->taskStatuses()
            ->orderBy('position')
            ->get();

        $tasksGroupedByStatus = $tasks->groupBy(function ($task) {
            return $task->status_id ?? 'no-status';
        });

        $kanban = $statuses->map(function ($status) use ($tasksGroupedByStatus) {
            $tasksForStatus = $tasksGroupedByStatus->get($status->id, collect());

            return [
                'status_id' => $status->id,
                'project_id' => $status->project_id,
                'status_name' => $status->name,
                'status_position' => $status->position,
                'tasks' => TaskResource::collection($tasksForStatus),
                'tasks_count' => $tasksForStatus->count(),
            ];
        })->sortBy('status_position')->values();

        $noStatusTasks = $tasksGroupedByStatus->get('no-status', collect());
        if ($noStatusTasks->isNotEmpty()) {
            $kanban->push([
                'status_id' => null,
                'project_id' => $projectId,
                'status_name' => 'No Status',
                'status_position' => 999,
                'tasks' => TaskResource::collection($noStatusTasks),
                'tasks_count' => $noStatusTasks->count(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $kanban,
            'total_tasks' => $tasks->count(),
            'project_id' => $projectId,
        ]);
    }
}
