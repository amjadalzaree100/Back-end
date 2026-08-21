<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Favorite>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        return [
            'user_id' => User::factory(),
            'favorite_user_id' => User::factory(),
        ];
    }
}