<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectReaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectReactionSeeder extends Seeder
{
    public function run(): void
    {
        $allUsers = User::orderBy('id')->pluck('id')->toArray();

        $publicProjects = Project::where('visibility', 'public')->get();

        foreach ($publicProjects as $project) {
            $memberIds = $project->users->pluck('id')->toArray();

            $targetReactions = rand(10, 20);

            // Each member may react once, so ensure the project has enough
            // members to reach the target (public projects attract members).
            if (count($memberIds) < $targetReactions) {
                foreach ($allUsers as $userId) {
                    if (count($memberIds) >= $targetReactions) {
                        break;
                    }

                    if (! in_array($userId, $memberIds)) {
                        $project->users()->attach($userId, ['role' => 'user']);
                        $memberIds[] = $userId;
                    }
                }
            }

            foreach (array_slice($memberIds, 0, $targetReactions) as $userId) {
                $reactionType = ProjectReaction::factory()->make()->reaction_type;

                ProjectReaction::updateOrCreate(
                    ['project_id' => $project->id, 'user_id' => $userId],
                    ['reaction_type' => $reactionType]
                );
            }
        }
    }
}