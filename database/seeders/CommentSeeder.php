<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::with('project.users')->get();

        if ($tasks->isEmpty()) {
            return;
        }

        // Aim for 100-200 comments total, distributed as 5-10 per task.
        $targetTotal = rand(100, 200);
        $perTask = (int) floor($targetTotal / $tasks->count());
        $perTask = max(5, min(10, $perTask));

        foreach ($tasks as $task) {
            $members = $task->project?->users->pluck('id')->toArray() ?? [];

            if (empty($members)) {
                continue;
            }

            for ($i = 0; $i < $perTask; $i++) {
                Comment::factory()->create([
                    'task_id' => $task->id,
                    'user_id' => $members[array_rand($members)],
                ]);
            }
        }
    }
}