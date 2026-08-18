<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectCreated
{
    use Dispatchable;

    public $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }
}
