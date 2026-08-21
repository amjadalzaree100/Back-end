<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead. Tests pass exact values via the
 * `create([...])` overrides anyway.
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;
        $slug = 'Project-'.$i.'-'.Str::lower(Str::random(6));

        return [
            'name' => Str::title(str_replace('-', ' ', $slug)),
            'description' => 'Project '.$i.' description.',
            'image' => null,
            'status' => 'active',
            'visibility' => 'public',
            'start_date' => null,
            'end_date' => null,
            'created_by' => User::factory(),
            'allow_join_requests' => true,
            'allow_commit' => true,
            'allow_reactions' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => 'paused']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }

    public function public(): static
    {
        return $this->state(fn () => ['visibility' => 'public']);
    }

    public function private(): static
    {
        return $this->state(fn () => ['visibility' => 'private']);
    }
}
