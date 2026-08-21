<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Rating>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        return [
            'rater_id' => User::factory(),
            'rated_user_id' => User::factory(),
            'project_id' => Project::factory(),
            'rating' => rand(1, 10),
        ];
    }
}