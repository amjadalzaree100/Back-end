<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Report>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $reasons = [
            'Inappropriate behavior',
            'Spam',
            'Harassment',
            'Hate speech',
            'Impersonation',
            'Privacy violation',
        ];

        $details = [
            'The user repeatedly posted offensive messages in the project feed.',
            'The account appears to be sending automated spam to other members.',
            'The user sent threatening messages through the messaging system.',
            'The user used discriminatory language against another member.',
            'The account is impersonating a known public figure.',
            'The user shared private information without consent.',
        ];

        $statuses = ['open', 'reviewed', 'dismissed'];

        return [
            'reporter_id' => User::factory(),
            'reported_user_id' => User::factory(),
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