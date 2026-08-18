<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskStatus\ReorderTaskStatusRequest;
use App\Http\Requests\TaskStatus\StoreTaskStatusRequest;
use App\Http\Requests\TaskStatus\UpdateTaskStatusRequest;
use App\Http\Resources\TaskStatusResource;
use App\Models\Project;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;


class TaskStatusController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        try {
            $this->checkProjectAccess($project);

            $statuses = $project->taskStatuses()
                ->withCount('tasks')
                ->orderBy('position')
                ->get();

            return response()->json([
                'success' => true,
                'data' => TaskStatusResource::collection($statuses),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch task statuses: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load task statuses. Please try again later.'
            ], 500);
        }
    }
    public function store(StoreTaskStatusRequest $request, Project $project): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            if (!$project->isOwner($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the project owner can create task statuses.'
                ], 403);
            }

            $existingStatus = $project->taskStatuses()
                ->where('name', $request->name)
                ->first();

            if ($existingStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'A status with this name already exists in this project',
                ], 409);
            }

            DB::beginTransaction();

            $existingCount = $project->taskStatuses()->count();

            $status = $project->taskStatuses()->create([
                'name' => $request->name,
                'position' => $existingCount + 1,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task status created successfully',
                'data' => new TaskStatusResource($status),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create task status: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'status_name' => $request->name,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task status. Please try again later.'
            ], 500);
        }
    }
    public function update(UpdateTaskStatusRequest $request, Project $project, TaskStatus $taskStatus): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            if (!$project->isOwner($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the project owner can update task statuses.'
                ], 403);
            }

            if ($taskStatus->project_id !== $project->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task status does not belong to this project',
                ], 404);
            }

            $existingStatus = $project->taskStatuses()
                ->where('name', $request->name)
                ->where('id', '!=', $taskStatus->id)
                ->first();

            if ($existingStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'A status with this name already exists in this project',
                ], 409);
            }

            DB::beginTransaction();

            $taskStatus->update([
                'name' => $request->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully',
                'data' => new TaskStatusResource($taskStatus),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update task status: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'status_id' => $taskStatus->id,
                'user_id' => $request->user()->id,
                'new_name' => $request->name,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task status. Please try again later.'
            ], 500);
        }
    }
    public function destroy(UpdateTaskStatusRequest $request, Project $project, TaskStatus $taskStatus): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            if (!$project->isOwner($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the project owner can delete task statuses.'
                ], 403);
            }

            if ($taskStatus->tasks()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete status with existing tasks. Move or delete tasks first.',
                ], 409);
            }

            DB::beginTransaction();

            $deletedPosition = $taskStatus->position;
            $taskStatus->delete();

            $project->taskStatuses()
                ->where('position', '>', $deletedPosition)
                ->decrement('position');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task status deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete task status: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'status_id' => $taskStatus->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task status. Please try again later.'
            ], 500);
        }
    }
    public function reorder(ReorderTaskStatusRequest $request, Project $project): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            if (!$project->isOwner($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the project owner can reorder task statuses.'
                ], 403);
            }

            DB::beginTransaction();

            foreach ($request->statuses as $statusData) {
                $status = TaskStatus::where('id', $statusData['id'])
                    ->where('project_id', $project->id)
                    ->first();

                if (!$status) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid status ID: ' . $statusData['id']
                    ], 422);
                }

                $status->update(['position' => $statusData['position']]);
            }

            DB::commit();

            $updatedStatuses = $project->taskStatuses()
                ->orderBy('position')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Task statuses reordered successfully',
                'data' => TaskStatusResource::collection($updatedStatuses),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reorder task statuses: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder task statuses. Please try again later.'
            ], 500);
        }
    }

    public function defaultStatuses(Request $request, Project $project): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            if (!$project->isOwner($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the project owner can create default statuses.'
                ], 403);
            }

            $defaultStatuses = ['To Do', 'In Progress', 'Done'];
            $existingCount = $project->taskStatuses()->count();

            if ($existingCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project already has statuses',
                ], 409);
            }

            DB::beginTransaction();

            foreach ($defaultStatuses as $index => $statusName) {
                $project->taskStatuses()->create([
                    'name' => $statusName,
                    'position' => $index + 1,
                ]);
            }

            DB::commit();

            $statuses = $project->taskStatuses()->orderBy('position')->get();

            return response()->json([
                'success' => true,
                'message' => 'Default statuses created successfully',
                'data' => TaskStatusResource::collection($statuses),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create default statuses: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create default statuses. Please try again later.'
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

}
