<?php

namespace Database\Seeders;

use App\Models\Chain;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChainProjectSeeder extends Seeder
{
    public function run(): void
    {
        $alaa = User::where('email', 'alaa.gbh0@gmail.com')->first();

        $chainsByProjects = [
            'Q3 Product Roadmap' => ['Website Redesign', 'Mobile App Development', 'Internal Dashboard'],
            'Mobile App Development' => ['Mobile App Development', 'API Integration'],
            'Infrastructure Migration' => ['Database Migration', 'API Integration', 'Internal Dashboard'],
            'Marketing Campaign' => ['Website Redesign', 'Internal Dashboard'],
        ];

        foreach (Chain::all() as $chain) {
            $creator = $chain->creator;

            // Preferred projects first, so each chain stays thematically coherent.
            $selected = collect();

            foreach ($chainsByProjects[$chain->name] ?? [] as $projectName) {
                $project = Project::where('name', $projectName)->first();
                if ($project && $project->hasUser($creator->id)) {
                    $selected->push($project);
                }
            }

            // Fill the chain up to 2-3 projects with any other project the
            // creator belongs to (never more than 3).
            foreach (Project::all() as $project) {
                if ($selected->count() >= 3) {
                    break;
                }

                if (! $selected->contains('id', $project->id) && $project->hasUser($creator->id)) {
                    $selected->push($project);
                }
            }

            // Last resort: hand the chain back to alaa (owner of every project).
            if ($selected->isEmpty()) {
                $chain->update(['created_by' => $alaa->id]);
                $selected = collect(Project::all())
                    ->filter(fn ($project) => $project->hasUser($alaa->id))
                    ->values();
            }

            foreach ($selected as $index => $project) {
                $chain->addProject($project->id, $index);
            }
        }
    }
}