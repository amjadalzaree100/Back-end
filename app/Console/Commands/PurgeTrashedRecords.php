<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Group;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\Task;
use App\Models\TaskAssignment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeTrashedRecords extends Command
{
    protected $signature = 'trash:purge {--days=30 : Number of days to keep trashed records}';
    protected $description = 'Permanently delete records that have been in the trash for more than the given number of days';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("Purging trashed records older than {$days} days...");

        $models = [
            'Tasks' => Task::class,
            'Task Assignments' => TaskAssignment::class,
            'Projects' => Project::class,
            'Groups' => Group::class,
            'Comments' => Comment::class,
            'Project Comments' => ProjectComment::class,
        ];

        $total = 0;

        foreach ($models as $label => $modelClass) {
            try {
                $count = $modelClass::onlyTrashed()
                    ->where('deleted_at', '<', $cutoff)
                    ->forceDelete();

                if ($count > 0) {
                    $this->info("Purged {$count} {$label}.");
                }

                $total += $count;
            } catch (\Exception $e) {
                Log::error("Failed to purge {$label}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to purge {$label}: {$e->getMessage()}");
            }
        }

        $this->info("Total purged: {$total} records.");
        return 0;
    }
}
