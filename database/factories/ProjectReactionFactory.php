<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectReaction>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and a weighted type pool instead.
 */
class ProjectReactionFactory extends Factory
{
    protected $model = ProjectReaction::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        // Weighted pool to approximate ~60% like, ~25% love, ~15% dislike.
        $types = [
            'like', 'like', 'like', 'like', 'like', 'like',
            'like', 'like', 'like', 'like', 'like', 'like',
            'love', 'love', 'love', 'love', 'love',
            'dislike', 'dislike', 'dislike',
        ];

        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'reaction_type' => $types[($i - 1) % count($types)],
        ];
    }

    public function reactionType(string $type): static
    {
        return $this->state(fn () => ['reaction_type' => $type]);
    }
}