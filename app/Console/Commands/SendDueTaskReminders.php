<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDueTaskReminders extends Command
{
    protected $signature = 'reminders:send-due-tasks';
    protected $description = 'Send notifications for tasks due within the next 24 hours';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('Checking for tasks due within 24 hours...');

        try {
            $now = Carbon::now();
            $deadline = $now->copy()->addHours(24);

            $tasks = Task::whereNull('completed_at')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$now, $deadline])
                ->with(['assignee', 'assignees', 'project', 'assignedGroup.members'])
                ->get();

            if ($tasks->isEmpty()) {
                $this->info('No due tasks found.');
                return 0;
            }

            $sentCount = 0;
            $errorCount = 0;

            foreach ($tasks as $task) {
                try {
                    $hoursLeft = $now->diffInHours($task->due_date, false);
                    $timeMsg = $hoursLeft > 0
                        ? "due in {$hoursLeft} hours"
                        : 'due now';

                    $title = 'Task Due Soon';
                    $message = "Task \"{$task->title}\" is {$timeMsg} in project: {$task->project->name}";
                    $actionUrl = "/projects/{$task->project_id}/tasks/{$task->id}";
                    $icon = '⏰';

                    $notifiedUsers = [];

                    if ($task->assignee) {
                        $this->notificationService->send(
                            $task->assignee->id,
                            $title,
                            $message,
                            'reminder',
                            ['task_id' => $task->id, 'due_date' => $task->due_date->toDateString()],
                            $actionUrl,
                            $icon
                        );
                        $notifiedUsers[] = $task->assignee->id;
                    }

                    $assigneeIds = $task->assigned_to ? [$task->assigned_to] : [];

                    foreach ($assigneeIds as $assigneeId) {
                        if (!in_array($assigneeId, $notifiedUsers)) {
                            $this->notificationService->send(
                                $assigneeId,
                                $title,
                                $message,
                                'reminder',
                                ['task_id' => $task->id, 'due_date' => $task->due_date->toDateString()],
                                $actionUrl,
                                $icon
                            );
                            $notifiedUsers[] = $assigneeId;
                        }
                    }

                    // Notify members of the assigned group (if this is a group task)
                    if ($task->assignedGroup) {
                        foreach ($task->assignedGroup->members as $member) {
                            if (!in_array($member->id, $notifiedUsers)) {
                                $this->notificationService->send(
                                    $member->id,
                                    $title,
                                    $message,
                                    'reminder',
                                    ['task_id' => $task->id, 'due_date' => $task->due_date->toDateString()],
                                    $actionUrl,
                                    $icon
                                );
                                $notifiedUsers[] = $member->id;
                            }
                        }
                    }

                    $sentCount++;
                    $this->info("Reminder sent for task #{$task->id}: {$task->title}");
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to send due task reminder', [
                        'task_id' => $task->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Sent: {$sentCount}, Errors: {$errorCount}");
            return 0;
        } catch (\Exception $e) {
            Log::error('SendDueTaskReminders command failed: ' . $e->getMessage());
            $this->error('Command failed.');
            return 1;
        }
    }
}
