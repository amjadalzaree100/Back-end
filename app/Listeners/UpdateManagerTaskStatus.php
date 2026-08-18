<?php

namespace App\Listeners;

use App\Events\ManagerTaskCompleted;
use App\Models\TaskStatus;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateManagerTaskStatus implements ShouldQueue
{
    public function handle(ManagerTaskCompleted $event): void
    {
        $task = $event->task;

        if (!$task->auto_status) {
            return;
        }

        $incompleteSubtasks = $task->subTasks()
            ->whereHas('taskAssignments', function ($query) {
                $query->whereNull('completed_at');
            })
            ->count();

        if ($incompleteSubtasks === 0 && $task->subTasks()->count() > 0) {
            $doneStatus = TaskStatus::where('name', 'Done')->first();

            if ($doneStatus) {
                $task->update([
                    'status_id' => $doneStatus->id,
                    'completed_at' => now()
                ]);
            }
        }
    }
}
