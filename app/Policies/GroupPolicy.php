<?php

namespace app\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /**
     * Determine if a user can view a group
     *
     * @param User $user The authenticated user
     * @param Group $group The group to view
     * @return bool
     */
    public function view(User $user, Group $group): bool
    {
        // Project owner can view any group
        // Group members can view their own group
        return $group->project->isOwner($user->id) || $group->isMember($user->id);
    }

    /**
     * Determine if a user can create a group
     *
     * @param User $user The authenticated user
     * @param Group $group The group context
     * @return bool
     */
    public function create(User $user, Group $group): bool
    {
        return $group->project->isOwner($user->id) || $group->project->isManager($user->id);
    }
    /**
     * Determine if a user can update a group
     *
     * @param User $user The authenticated user
     * @param Group $group The group to update
     * @return bool
     */
    public function update(User $user, Group $group): bool
    {
        // Project owner or group manager can update group info
        return $group->project->isOwner($user->id) || $group->isManager($user->id);
    }

    /**
     * Determine if a user can delete a group
     *
     * @param User $user The authenticated user
     * @param Group $group The group to delete
     * @return bool
     */
    public function delete(User $user, Group $group): bool
    {
        // Only project owner can delete groups
        return $group->project->isOwner($user->id);
    }

    /**
     * Determine if a user can add a member to a group
     *
     * @param User $user The authenticated user
     * @param Group $group The group to add member to
     * @return bool
     */
    public function addMember(User $user, Group $group): bool
    {
        // Project owner or group manager can add members
        return $group->project->isOwner($user->id) || $group->isManager($user->id);
    }

    /**
     * Determine if a user can remove a member from a group
     *
     * @param User $user The authenticated user
     * @param Group $group The group to remove member from
     * @return bool
     */
    public function removeMember(User $user, Group $group): bool
    {
        // Project owner or group manager can remove members
        return $group->project->isOwner($user->id) || $group->isManager($user->id);
    }

    /**
     * Determine if a user can transfer manager role to another user
     *
     * @param User $user The authenticated user
     * @param Group $group The group to transfer manager role in
     * @return bool
     */
    public function transferManager(User $user, Group $group): bool
    {
        // Current manager can transfer their role
        // Project owner can also transfer manager role
        return $group->isManager($user->id) || $group->project->isOwner($user->id);
    }

    /**
     * Determine if a user can set/change the group manager
     */
    public function setManager(User $user, Group $group): bool
    {
        // Project owner, project manager, or current group manager can set manager
        return $group->project->isOwner($user->id)
            || $group->project->isManager($user->id)
            || $group->isManager($user->id);
    }
}
