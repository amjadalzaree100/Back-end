<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine if a user can view a task
     *
     * @param User $user The authenticated user
     * @param Task $task The task to view
     * @return bool
     */
    public function view(User $user, Task $task): bool
    {
        $project = $task->project;

        // Project owner can view any task
        if ($project->isOwner($user->id)) {
            return true;
        }

        // Manager or member can view tasks within their group
        if ($task->group_id) {
            $group = $task->group;
            if ($group && ($group->isManager($user->id) || $group->isMember($user->id))) {
                return true;
            }
        }

        // User can view tasks assigned to them
        return $task->taskAssignments()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine if a user can create a direct project task
     *
     * @param User $user The authenticated user
     * @param Task $task The parent task context
     * @return bool
     */
    public function createProjectTask(User $user, Task $task): bool
    {
        // Project owner or manager can create project tasks
        return $task->project->isOwner($user->id) || $task->project->isManager($user->id);
    }

    /**
     * Determine if a user can create a group task (assign task to entire group)
     *
     * @param User $user The authenticated user
     * @param Task $task The parent task context
     * @return bool
     */
    public function createGroupTask(User $user, Task $task): bool
    {
        // Only project owner can assign tasks to entire groups
        return $task->project->isOwner($user->id);
    }

    /**
     * Determine if a user can create a manager task (task with subtasks, cannot be assigned)
     *
     * @param User $user The authenticated user
     * @param Task $task The parent task context
     * @return bool
     */
    public function createManagerTask(User $user, Task $task): bool
    {
        // User must be manager of the group this task belongs to
        if ($task->group_id) {
            $group = $task->group;
            return $group && $group->isManager($user->id);
        }
        // Or project owner
        return $task->project->isOwner($user->id);
    }

    /**
     * Determine if a user can create a subtask under a parent task
     *
     * @param User $user The authenticated user
     * @param Task $parentTask The parent task
     * @return bool
     */
    public function createSubTask(User $user, Task $parentTask): bool
    {
        // Parent task must allow subtasks
        if (!$parentTask->allow_subtasks) {
            return false;
        }

        // Project owner can create subtasks for any task
        if ($parentTask->project->isOwner($user->id)) {
            return true;
        }

        // Group manager can create subtasks for tasks within their group
        if ($parentTask->group_id) {
            $group = $parentTask->group;
            return $group && $group->isManager($user->id);
        }

        return false;
    }

    /**
     * Determine if a user can update a task
     *
     * @param User $user The authenticated user
     * @param Task $task The task to update
     * @return bool
     */
    public function update(User $user, Task $task): bool
    {
        // Project owner can update any task
        if ($task->project->isOwner($user->id)) {
            return true;
        }

        // Task creator can update their own tasks
        return $task->created_by === $user->id;
    }

    /**
     * Determine if a user can delete a task
     *
     * @param User $user The authenticated user
     * @param Task $task The task to delete
     * @return bool
     */
    public function delete(User $user, Task $task): bool
    {
        // Project owner can delete any task
        if ($task->project->isOwner($user->id)) {
            return true;
        }

        // Task creator can delete their own tasks only if no subtasks exist
        if ($task->created_by === $user->id && $task->subTasks()->count() === 0) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a user can assign a task to other users
     *
     * @param User $user The authenticated user
     * @param Task $task The task to assign
     * @return bool
     */
    public function assign(User $user, Task $task): bool
    {
        // Task must be assignable
        if (!$task->can_be_assigned) {
            return false;
        }

        // Project owner can assign any task
        if ($task->project->isOwner($user->id)) {
            return true;
        }

        // Group manager can assign tasks within their group
        if ($task->group_id) {
            $group = $task->group;
            return $group && $group->isManager($user->id);
        }

        return false;
    }
}
