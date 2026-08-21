<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $adjectives = ['Core', 'Design', 'Backend', 'Frontend', 'QA', 'DevOps', 'Mobile', 'Data'];

        return [
            'project_id' => Project::factory(),
            'name' => $adjectives[($i - 1) % count($adjectives)].' Team '.$i,
            'description' => 'The group responsible for '.Str::lower($adjectives[($i - 1) % count($adjectives)]).' tasks.',
            'avatar' => null,
            'manager_id' => null,
            'created_by' => null,
            'is_active' => true,
        ];
    }
}
