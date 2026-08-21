<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectReport>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class ProjectReportFactory extends Factory
{
    protected $model = ProjectReport::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $reasons = [
            'Project contains inappropriate content',
            'Spam project',
            'Violates terms',
            'Plagiarized content',
            'Misleading project description',
            'Copyright infringement',
        ];

        $details = [
            'The project description contains offensive or explicit material.',
            'The project appears to be created by a spam account to advertise a service.',
            'The project violates the platform terms of service.',
            'The project contains content copied from another source without permission.',
            'The project description does not match what the project actually offers.',
            'The project uses copyrighted assets without authorization.',
        ];

        $statuses = ['open', 'reviewed', 'dismissed'];

        return [
            'reporter_id' => User::factory(),
            'reported_project_id' => Project::factory(),
            'reason' => $reasons[($i - 1) % count($reasons)],
            'details' => $details[($i - 1) % count($details)],
            'status' => $statuses[($i - 1) % count($statuses)],
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open']);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => ['status' => 'reviewed']);
    }

    public function dismissed(): static
    {
        return $this->state(fn () => ['status' => 'dismissed']);
    }
}