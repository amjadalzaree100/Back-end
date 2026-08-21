<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectComment;
use Illuminate\Database\Seeder;

class ProjectCommentSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::with('users')->get();

        $topLevelComments = [
            'Welcome to the project!',
            'Please check the new milestone',
            'Great teamwork this sprint',
            "Don't forget to update your tasks",
            "Meeting notes from today's standup: we agreed on the new timeline, everyone should update their tasks by Friday. Please review the action items and let me know if you have any questions.",
            "I've updated the project description with the new scope. Please review it before our next sync.",
            'Congrats everyone, we hit the sprint goal ahead of schedule!',
            'Reminder: the deadline for this milestone has been moved to next Monday. Adjust your plans accordingly.',
            "Let's keep the momentum going, only a few tasks left before we wrap up.",
            'I have uploaded the meeting notes, feel free to add any feedback.',
        ];

        $replyComments = [
            'Sounds good, I will take care of it.',
            'Thanks for the update, noted!',
            'I agree, let us move forward with this plan.',
            'Good point, I will share the details in the next meeting.',
            'Already done, check the updated tasks.',
            'Could you clarify the timeline for the next milestone?',
            'Will do, thanks for the reminder.',
        ];

        foreach ($projects as $project) {
            $members = $project->users->pluck('id')->toArray();

            if (empty($members)) {
                continue;
            }

            // 5-8 comments per project (25-40 total) with 2-3 reply threads.
            $commentCount = rand(5, 8);
            $threadCount = min(rand(2, 3), (int) floor($commentCount / 2));
            $topLevelCount = $commentCount - $threadCount;

            $topLevels = [];

            for ($i = 0; $i < $topLevelCount; $i++) {
                $comment = ProjectComment::factory()->create([
                    'project_id' => $project->id,
                    'user_id' => $members[array_rand($members)],
                    'content' => $topLevelComments[array_rand($topLevelComments)],
                    'parent_id' => null,
                ]);

                $topLevels[] = $comment;
            }

            if (empty($topLevels)) {
                continue;
            }

            shuffle($topLevels);
            $parents = array_slice($topLevels, 0, $threadCount);

            foreach ($parents as $parent) {
                ProjectComment::factory()->create([
                    'project_id' => $project->id,
                    'user_id' => $members[array_rand($members)],
                    'content' => $replyComments[array_rand($replyComments)],
                    'parent_id' => $parent->id,
                    'created_at' => $parent->created_at->addMinutes(rand(30, 720)),
                ]);
            }
        }
    }
}