<?php

namespace Database\Factories;

use App\Models\BlockedUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlockedUser>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class BlockedUserFactory extends Factory
{
    protected $model = BlockedUser::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $reasons = [
            'Spam',
            'Harassment',
            'Personal conflict',
            'Inappropriate behavior',
            'Frequent abuse reports',
        ];

        $reason = rand(0, 3) === 0 ? null : $reasons[($i - 1) % count($reasons)];

        return [
            'user_id' => User::factory(),
            'blocked_user_id' => User::factory(),
            'reason' => $reason,
        ];
    }
}