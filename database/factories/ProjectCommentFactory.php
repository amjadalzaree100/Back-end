<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectComment>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and predefined content pools instead.
 */
class ProjectCommentFactory extends Factory
{
    protected $model = ProjectComment::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $contents = [
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

        $replies = [
            'Sounds good, I will take care of it.',
            'Thanks for the update, noted!',
            'I agree, let us move forward with this plan.',
            'Good point, I will share the details in the next meeting.',
            'Already done, check the updated tasks.',
            'Could you clarify the timeline for the next milestone?',
            'Will do, thanks for the reminder.',
        ];

        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'content' => $contents[($i - 1) % count($contents)],
            'parent_id' => null,
            'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
            'updated_at' => now(),
        ];
    }

    public function content(string $content): static
    {
        return $this->state(fn () => ['content' => $content]);
    }

    public function reply(?int $parentId): static
    {
        return $this->state(fn () => ['parent_id' => $parentId]);
    }
}