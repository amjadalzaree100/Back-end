<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Rating;
use Illuminate\Database\Seeder;

class RatingSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::all();

        if ($projects->isEmpty()) {
            return;
        }

        foreach ($projects as $project) {
            $memberIds = $project->users()->pluck('users.id')->toArray();

            if (count($memberIds) < 2) {
                continue;
            }

            // Build every ordered pair of members (rater != rated) so both
            // ends are always members of the same project.
            $pairs = [];

            foreach ($memberIds as $raterId) {
                foreach ($memberIds as $ratedId) {
                    if ($raterId !== $ratedId) {
                        $pairs[] = [
                            'rater' => $raterId,
                            'rated' => $ratedId,
                        ];
                    }
                }
            }

            shuffle($pairs);

            $count = min(rand(5, 10), count($pairs));

            for ($i = 0; $i < $count; $i++) {
                Rating::factory()->create([
                    'project_id' => $project->id,
                    'rater_id' => $pairs[$i]['rater'],
                    'rated_user_id' => $pairs[$i]['rated'],
                    'rating' => $this->weightedRating(),
                ]);
            }
        }
    }

    protected function weightedRating(): int
    {
        $roll = rand(1, 100);

        if ($roll <= 10) {
            return rand(1, 3);
        }

        if ($roll <= 35) {
            return rand(4, 6);
        }

        if ($roll <= 80) {
            return rand(7, 8);
        }

        return rand(9, 10);
    }
}