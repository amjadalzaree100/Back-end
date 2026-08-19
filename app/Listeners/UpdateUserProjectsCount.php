<?php

namespace App\Listeners;

use App\Events\ProjectCreated;

class UpdateUserProjectsCount
{
    public function handle(ProjectCreated $event)
    {
        $user = $event->project->creator;

        if ($user && $user->profile) {
            $projectsCount = $user->projects()->count();
            $user->profile->update(['projects_count' => $projectsCount]);
        }
    }
}
