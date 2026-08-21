<?php

namespace Database\Factories;

use App\Models\Chain;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chain>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter instead.
 */
class ChainFactory extends Factory
{
    protected $model = Chain::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $prefixes = [
            'Q'.rand(1, 4).' Roadmap',
            'Product Launch',
            'Feature Sprint',
            'Marketing Campaign',
            'Holiday Release',
            'Bug Fixing Blitz',
            'Client Onboarding',
            'Platform Migration',
        ];

        return [
            'name' => $prefixes[($i - 1) % count($prefixes)].($i > count($prefixes) ? ' '.$i : ''),
            'created_by' => User::factory(),
        ];
    }
}
