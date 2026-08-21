<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\TaskStatusHistory;
use App\Models\User;

class ProjectMemberCleanupService
{
    /**
     * Cleanup when a manager leaves or is expelled from a project.
     * - Nulls manager_id on groups they managed
     * - Clears assigned_to and status_id on their tasks and subtasks
     *
     * @return int Number of groups they were managing
     */
    public function cleanupManagerRole(User $user, Project $project): int
    {
        // Count groups managed before nullifying
        $managedGroupsCount = Group::where('project_id', $project->id)
            ->where('manager_id', $user->id)
            ->count();

        // Nullify groups managed by this user in this project
        Group::where('project_id', $project->id)
            ->where('manager_id', $user->id)
            ->update(['manager_id' => null]);

        // Clear task assignments
        $this->clearUserTasks($user, $project);

        return $managedGroupsCount;
    }

    /**
     * Cleanup when a regular user leaves or is expelled from a project.
     * - Clears assigned_to and status_id on their tasks and subtasks
     */
    public function cleanupUserRole(User $user, Project $project): void
    {
        $this->clearUserTasks($user, $project);
    }

    /**
     * Clear all task assignments for a user in a project.
     * Handles both top-level tasks and subtasks.
     * Sets both assigned_to and status_id to null (they are linked).
     */
    public function clearUserTasks(User $user, Project $project): void
    {
        // Get all tasks assigned to this user in the project (including subtasks)
        $tasks = Task::where('project_id', $project->id)
            ->where('assigned_to', $user->id)
            ->get();

        foreach ($tasks as $task) {
            $this->clearTask($task, $user->id);
        }
    }

    /**
     * Clear a single task's assignment and status.
     * Also handles subtasks recursively.
     */
    private function clearTask(Task $task, int $userId): void
    {
        // Skip if already cleared (prevents duplicate processing when subtasks
        // appear in both the flat query result and the recursive descent)
        if ($task->assigned_to !== $userId) {
            return;
        }

        $previousStatusId = $task->status_id;

        // Record assignment history
        TaskAssignmentHistory::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'assigned_by' => $userId,
            'action' => 'unassigned',
            'assigned_at' => now(),
        ]);

        // Record status history (transition to null)
        if (!is_null($previousStatusId)) {
            TaskStatusHistory::create([
                'task_id' => $task->id,
                'from_status_id' => $previousStatusId,
                'to_status_id' => null,
                'changed_by' => $userId,
                'changed_at' => now(),
            ]);
        }

        // Update task (bypass model events so archived tasks can be updated)
        $task->withoutEvents(function () use ($task) {
            $task->update([
                'assigned_to' => null,
                'status_id' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);
        });

        // Handle subtasks recursively
        $subtasks = Task::where('parent_task_id', $task->id)
            ->where('assigned_to', $userId)
            ->get();

        foreach ($subtasks as $subtask) {
            $this->clearTask($subtask, $userId);
        }
    }

    /**
     * Count how many groups a user manages in a project.
     */
    public function countManagedGroups(User $user, Project $project): int
    {
        return Group::where('project_id', $project->id)
            ->where('manager_id', $user->id)
            ->count();
    }

    /**
     * Check if a project has any managers (excluding the owner).
     */
    public function hasManagers(Project $project): bool
    {
        return $project->users()
            ->wherePivotIn('role', ['manager'])
            ->exists();
    }
}