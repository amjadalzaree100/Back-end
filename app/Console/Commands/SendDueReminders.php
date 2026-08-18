<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send due reminders using the existing notification system';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('Checking for due reminders...');

        try {
            $reminders = Reminder::where('remind_at', '<=', Carbon::now())
                ->where('status', 'pending')
                ->with(['user', 'tasks'])
                ->get();

            if ($reminders->isEmpty()) {
                $this->info('No due reminders found.');
                return 0;
            }

            $sentCount = 0;
            $errorCount = 0;

            foreach ($reminders as $reminder) {
                try {
                    // تأكد من أن $reminder هو نموذج حقيقي
                    $reminderModel = $reminder instanceof Reminder ? $reminder : Reminder::find($reminder->id);
                    if (!$reminderModel) {
                        throw new \Exception("Reminder model not found for ID: {$reminder->id}");
                    }

                    $taskNames = $reminderModel->tasks->pluck('title')->implode(', ');
                    $actionUrl = '/reminders';
                    $icon = '🔔';

                    $this->notificationService->send(
                        $reminderModel->user_id,
                        $reminderModel->title,
                        $reminderModel->message ?? 'Task reminder : ' . $taskNames,
                        'reminder',
                        [
                            'reminder_id' => $reminderModel->id,
                            'remind_at' => $reminderModel->remind_at->toISOString(),
                            'task_ids' => $reminderModel->tasks->pluck('id'),
                        ],
                        $actionUrl,
                        $icon
                    );

                    $reminderModel->update(['status' => 'sent']);
                    $sentCount++;
                    $this->info("Reminder #{$reminderModel->id} sent.");
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to send reminder via NotificationService', [
                        'reminder_id' => $reminder->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Sent: {$sentCount}, Errors: {$errorCount}");
            return 0;
        } catch (\Exception $e) {
            Log::error('SendDueReminders command failed: ' . $e->getMessage());
            $this->error('Command failed.');
            return 1;
        }
    }
}
