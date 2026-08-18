<?php

namespace App\Listeners;

use App\Events\UserJoinedProject;

class UpdateUserProjectsCountOnJoin
{
    public function handle(UserJoinedProject $event)
    {
        $user = $event->user;

        if ($user && $user->profile) {
            $projectsCount = $user->projects()->count();
            $user->profile->update(['projects_count' => $projectsCount]);
        }
    }
}
