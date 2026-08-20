<?php

namespace App\Http\Controllers\api;

use App\Events\TaskNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaskDependency\AddDependencyRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskDependencyController extends Controller
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

        $dependencies = $task->dependencies()->get();
        $dependents = $task->dependents()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'is_started' => $task->isStarted(),
                'is_completed' => $task->isCompleted(),
                'is_blocked' => $task->isBlocked(),
                'can_be_started' => $task->canBeStarted(),
                'can_be_completed' => $task->canBeCompleted(),
                'dependencies' => $dependencies->map(fn($dep) => [
                    'id' => $dep->id,
                    'title' => $dep->title,
                    'status_id' => $dep->status_id,
                    'status' => $dep->status?->name,
                    'is_started' => $dep->isStarted(),
                    'is_completed' => $dep->isCompleted(),
                    'type' => $dep->pivot->type,
                    'type_label' => $task->getTypeLabel($dep->pivot->type),
                    'type_description' => $task->getTypeDescription($dep->pivot->type),
                ]),
                'dependents' => $dependents->map(fn($dep) => [
                    'id' => $dep->id,
                    'title' => $dep->title,
                    'status_id' => $dep->status_id,
                    'status' => $dep->status?->name,
                    'is_started' => $dep->isStarted(),
                    'is_completed' => $dep->isCompleted(),
                    'type' => $dep->pivot->type,
                    'type_label' => $task->getTypeLabel($dep->pivot->type),
                ]),
                'total_dependencies' => $dependencies->count(),
                'total_dependents' => $dependents->count(),
            ],
        ]);
    }

    public function addDependency(AddDependencyRequest $request, Project $project, Task $task): JsonResponse
    {
        if ($task->is_archived) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot manage dependencies on an archived task.',
            ], 403);
        }
        $this->checkProjectManager($project);

        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        $dependsOnTaskId = $request->depends_on_task_id;
        $type = $request->type ?? 'FS';

        $dependsOnTask = Task::where('id', $dependsOnTaskId)
            ->where('project_id', $project->id)
            ->first();

        if (!$dependsOnTask) {
            return response()->json([
                'success' => false,
                'message' => 'Dependency task does not belong to this project',
            ], 404);
        }

        if ($task->id === $dependsOnTaskId) {
            return response()->json([
                'success' => false,
                'message' => 'A task cannot depend on itself',
            ], 422);
        }

        if ($task->dependencies()->where('depends_on_task_id', $dependsOnTaskId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This dependency already exists',
            ], 409);
        }

        try {
            DB::beginTransaction();

            $task->dependencies()->attach($dependsOnTaskId, ['type' => $type]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add dependency: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'depends_on_task_id' => $dependsOnTaskId,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add dependency. Please try again later.',
            ], 500);
        }

        $userIds = [];
        if ($task->assigned_to) {
            $userIds[] = $task->assigned_to;
        }
        $userIds = array_merge($userIds, $task->assigned_to ? [$task->assigned_to] : []);
        $userIds = array_unique($userIds);

        if (!empty($userIds)) {
            TaskNotificationEvent::dispatch(
                userIds: $userIds,
                scenario: 'dependency_added',
                task: $task,
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Dependency added successfully',
            'data' => [
                'task_id' => $task->id,
                'depends_on_task_id' => $dependsOnTaskId,
                'type' => $type,
                'type_label' => $task->getTypeLabel($type),
                'type_description' => $task->getTypeDescription($type),
            ],
        ], 201);
    }

    public function removeDependency(Project $project, Task $task, int $dependsOnTaskId): JsonResponse
    {
        if ($task->is_archived) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot manage dependencies on an archived task.',
            ], 403);
        }
        $this->checkProjectManager($project);

        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        if (!$task->dependencies()->where('depends_on_task_id', $dependsOnTaskId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Dependency does not exist',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $task->dependencies()->detach($dependsOnTaskId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dependency removed successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove dependency',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateDependencyType(AddDependencyRequest $request, Project $project, Task $task, int $dependsOnTaskId): JsonResponse
    {
        $this->checkProjectManager($project);

        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project',
            ], 404);
        }

        if (!$task->dependencies()->where('depends_on_task_id', $dependsOnTaskId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Dependency does not exist',
            ], 404);
        }

        $type = $request->type ?? 'FS';

        try {
            DB::beginTransaction();

            $task->dependencies()->updateExistingPivot($dependsOnTaskId, ['type' => $type]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dependency type updated successfully',
                'data' => [
                    'task_id' => $task->id,
                    'depends_on_task_id' => $dependsOnTaskId,
                    'type' => $type,
                    'type_label' => $task->getTypeLabel($type),
                    'type_description' => $task->getTypeDescription($type),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update dependency type',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            abort(403, 'You do not have permission to manage task dependencies');
        }
    }
}
