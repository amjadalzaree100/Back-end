<?php

namespace Database\Factories;

use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMember>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter instead.
 */
class GroupMemberFactory extends Factory
{
    protected $model = GroupMember::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        return [
            'group_id' => GroupFactory::new(),
            'user_id' => User::factory(),
            'added_by' => User::factory(),
            'joined_at' => now(),
        ];
    }
}
