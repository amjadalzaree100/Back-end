<?php

namespace App\Events;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserJoinedProject
{
    use Dispatchable;

    public $user;
    public $project;

    public function __construct(User $user, Project $project)
    {
        $this->user = $user;
        $this->project = $project;
    }
}
