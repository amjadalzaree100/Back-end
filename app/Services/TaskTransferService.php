<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Project;
use App\Models\TaskTransfer;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class TaskTransferService
{
    protected ChainService $chainService;

    public function __construct(ChainService $chainService)
    {
        $this->chainService = $chainService;
    }

    public function transfer(int $taskId, int $targetProjectId, int $userId, ?string $note = null): Task
    {
        $task = Task::with(['project', 'subTasks'])->findOrFail($taskId);
        $targetProject = Project::findOrFail($targetProjectId);

        // Validate transfer conditions
        $this->validateTransfer($task, $targetProject);

        $sourceProject = $task->project;
        $defaultStatus = $targetProject->taskStatuses()->first();
        $newStatusId = $defaultStatus ? $defaultStatus->id : null;

        DB::beginTransaction();

        try {
            // 1. Clone the parent task (transferred tasks are unassigned)
            $newTask = $task->replicate();
            $newTask->project_id = $targetProjectId;
            $newTask->assigned_to = null;
            $newTask->assigned_group_id = null;
            $newTask->is_archived = false;
            $newTask->parent_task_id = null;
            $newTask->transferred_from_task_id = $task->id;
            $newTask->transferred_to_task_id = null;

            if ($newStatusId) {
                $newTask->status_id = $newStatusId;
            }

            $newTask->save();

            // Clean parent task relationships
            $newTask->dependencies()->detach();
            $newTask->dependents()->detach();

            // 2. Clone all subtasks (transferred subtasks are unassigned)
            $subtaskMapping = [];

            foreach ($task->subTasks as $subTask) {
                $newSubTask = $subTask->replicate();
                $newSubTask->project_id = $targetProjectId;
                $newSubTask->parent_task_id = $newTask->id;
                $newSubTask->assigned_to = null;
                $newSubTask->assigned_group_id = null;
                $newSubTask->is_archived = false;
                $newSubTask->transferred_from_task_id = $subTask->id;
                $newSubTask->transferred_to_task_id = null;

                if ($newStatusId) {
                    $newSubTask->status_id = $newStatusId;
                }

                $newSubTask->save();

                // Clean subtask relationships
                $newSubTask->dependencies()->detach();
                $newSubTask->dependents()->detach();

                $subtaskMapping[$subTask->id] = $newSubTask;
            }

            // 3. Archive the original task and point it to the clone
            $task->archive();
            $task->update(['transferred_to_task_id' => $newTask->id]);

            // 4. Create transfer record for the parent task
            TaskTransfer::create([
                'task_id' => $newTask->id,
                'from_project_id' => $sourceProject->id,
                'to_project_id' => $targetProject->id,
                'from_task_id' => $task->id,
                'to_task_id' => $newTask->id,
                'transferred_by' => $userId,
                'note' => $note . ' (Parent task)',
                'transferred_at' => now(),
            ]);

            // 5. Create transfer records for all subtasks
            foreach ($task->subTasks as $subTask) {
                $newSubTask = $subtaskMapping[$subTask->id] ?? null;

                if ($newSubTask) {
                    TaskTransfer::create([
                        'task_id' => $newSubTask->id,
                        'from_project_id' => $sourceProject->id,
                        'to_project_id' => $targetProject->id,
                        'from_task_id' => $subTask->id,
                        'to_task_id' => $newSubTask->id,
                        'transferred_by' => $userId,
                        'note' => $note . ' (Subtask)',
                        'transferred_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return $newTask->load(['project', 'status', 'creator', 'subTasks']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function validateTransfer(Task $task, Project $targetProject): void
    {
        // 1. Check if task is already archived
        if ($task->is_archived) {
            throw ValidationException::withMessages([
                'task' => 'Archived tasks cannot be transferred.'
            ]);
        }

        // 2. Check if subtasks are completed
        if (!$task->canBeTransferred()) {
            throw ValidationException::withMessages([
                'task' => 'Cannot transfer task because it has incomplete subtasks.'
            ]);
        }

        // 3. Check if both projects are in the same chain
        $sourceChain = $this->chainService->getProjectChain($task->project_id);
        $targetChain = $this->chainService->getProjectChain($targetProject->id);

        if (!$sourceChain || !$targetChain || $sourceChain->id !== $targetChain->id) {
            throw ValidationException::withMessages([
                'target_project' => 'Target project must be in the same chain.'
            ]);
        }

        // 4. Check if target project is different from source
        if ($task->project_id === $targetProject->id) {
            throw ValidationException::withMessages([
                'target_project' => 'Target project must be different from source project.'
            ]);
        }

        // 5. Check if user is allowed to transfer (owner of source project)
        // This can be extended for project managers as needed
        // We'll handle authorization in the controller
    }

    public function getTransferHistory(int $taskId)
    {
        return TaskTransfer::where('task_id', $taskId)
            ->orWhere('from_task_id', $taskId)
            ->orWhere('to_task_id', $taskId)
            ->with(['fromProject', 'toProject', 'transferredBy'])
            ->orderBy('transferred_at', 'asc')
            ->get();
    }
}