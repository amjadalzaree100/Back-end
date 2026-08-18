<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

class TaskCompleted
{
    use Dispatchable;

    public $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }
}
