<?php

namespace Database\Seeders;

use App\Models\FavoriteProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteProjectSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        $alaa = $users->firstWhere('email', 'alaa.gbh0@gmail.com') ?? $users->first();

        $others = $users->where('id', '!=', $alaa->id)->shuffle()->values();

        // alaa favorites 2-3 projects; most other users favorite 2 projects
        // and the rest favorite 1, keeping the total within the 15-25 range.
        $targetCounts = [
            $alaa->id => rand(2, 3),
        ];

        $double = rand(5, 7);

        foreach ($others as $index => $user) {
            $targetCounts[$user->id] = $index < $double ? 2 : 1;
        }

        foreach ($users as $user) {
            // A user may only favorite projects they are a member of, or
            // projects that are public.
            $memberIds = $user->projects()->pluck('projects.id')->toArray();
            $publicIds = Project::where('visibility', 'public')->pluck('id')->toArray();

            $candidates = array_values(array_unique(array_merge($memberIds, $publicIds)));

            $favorited = 0;

            while ($favorited < $targetCounts[$user->id] && ! empty($candidates)) {
                $projectId = $candidates[array_rand($candidates)];
                $candidates = array_values(array_diff($candidates, [$projectId]));

                // Respect the unique constraint on (user_id, project_id).
                if (FavoriteProject::where('user_id', $user->id)
                    ->where('project_id', $projectId)
                    ->exists()) {
                    continue;
                }

                FavoriteProject::factory()->create([
                    'user_id' => $user->id,
                    'project_id' => $projectId,
                ]);

                $favorited++;
            }
        }
    }
}