<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and predefined content pools instead.
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $contents = [
            'Great progress on this!',
            'Can you add more details to the description?',
            "I'll review this tomorrow",
            'Fixed the issue you mentioned',
            "Let's discuss this in the next meeting",
            'This is blocked by another task',
            'Updated the requirements',
            "I've gone through the implementation and it looks solid so far. The edge cases are handled well, but we should add a few more tests before merging.",
            'Just finished reviewing the changes. Everything looks good on my end, please proceed with the deployment.',
            'I noticed a small bug in the logic when handling empty inputs. Can you take a look when you get a chance?',
            'We should probably split this task into smaller subtasks to make progress easier to track.',
            'The database query here is running a bit slow in production. Let us optimize it before the next release.',
            'Good work, but please remember to update the documentation as well.',
            'I am going to pick this up after I finish the current sprint tasks.',
            'Could you clarify the acceptance criteria for this task?',
            'I ran the tests locally and everything passes. Ready to merge whenever you are.',
        ];

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'content' => $contents[($i - 1) % count($contents)],
            'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
            'updated_at' => now(),
        ];
    }

    public function content(string $content): static
    {
        return $this->state(fn () => ['content' => $content]);
    }
}