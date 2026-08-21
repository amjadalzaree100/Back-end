<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $alaa = User::where('email', 'alaa.gbh0@gmail.com')->first();

        $groupTemplates = [
            'Website Redesign' => [
                ['name' => 'Frontend Team', 'manager' => 'alaa', 'description' => 'Responsible for the UI components, layout and mobile responsiveness of the redesigned website.'],
                ['name' => 'Design Team', 'manager' => 'other', 'description' => 'Owns the wireframes, prototypes, design system and brand consistency for the new website.'],
                ['name' => 'QA Team', 'manager' => 'other', 'description' => 'Handles cross-browser testing, accessibility checks and release sign-off for the website.'],
            ],
            'Mobile App Development' => [
                ['name' => 'Mobile Developers', 'manager' => 'alaa', 'description' => 'Builds the cross-platform mobile app on iOS and Android using a shared codebase.'],
                ['name' => 'Backend Developers', 'manager' => 'other', 'description' => 'Maintains the API layer, authentication and data sync for the mobile app.'],
                ['name' => 'QA Team', 'manager' => 'other', 'description' => 'Runs device-matrix testing and performance validation for the mobile app.'],
            ],
            'Database Migration' => [
                ['name' => 'Data Engineering Team', 'manager' => 'alaa', 'description' => 'Plans and executes the ETL jobs for moving legacy data into the new PostgreSQL schema.'],
                ['name' => 'DevOps Team', 'manager' => 'other', 'description' => 'Manages the zero-downtime cutover, backups and rollback procedures for the migration.'],
            ],
            'API Integration' => [
                ['name' => 'Integration Engineers', 'manager' => 'alaa', 'description' => 'Builds the adapters for the payment, notification and analytics third-party APIs.'],
                ['name' => 'Backend Developers', 'manager' => 'other', 'description' => 'Owns the internal endpoints, webhooks and payload validation for the integrations.'],
            ],
            'Internal Dashboard' => [
                ['name' => 'Frontend Team', 'manager' => 'alaa', 'description' => 'Builds the dashboard UI, charts and manager-facing views for team productivity.'],
                ['name' => 'Data Analytics Team', 'manager' => 'other', 'description' => 'Defines the metrics, aggregates the data and maintains the reporting queries.'],
            ],
        ];

        foreach ($groupTemplates as $projectName => $templates) {
            $project = Project::where('name', $projectName)->first();
            if (! $project) {
                continue;
            }

            $members = $project->users()->get();
            if ($members->isEmpty()) {
                continue;
            }

            foreach ($templates as $template) {
                if ($template['manager'] === 'alaa' && $members->contains('id', $alaa->id)) {
                    $managerId = $alaa->id;
                } else {
                    $candidates = $members->pluck('id')
                        ->reject(fn ($id) => $id === $alaa->id)
                        ->values();

                    $managerId = $candidates->isEmpty() ? $alaa->id : $candidates->random();
                }

                // ~85% of groups are active, keep a couple archived for realism
                $isActive = rand(1, 10) > 2;

                $group = Group::firstOrCreate(
                    ['project_id' => $project->id, 'name' => $template['name']],
                    [
                        'description' => $template['description'],
                        'avatar' => null,
                        'manager_id' => $managerId,
                        'created_by' => $alaa->id,
                        'is_active' => $isActive,
                    ]
                );

                // Member pool comes from the project's users, manager always included first.
                $pool = $members->pluck('id')
                    ->reject(fn ($id) => $id === $managerId)
                    ->shuffle()
                    ->prepend($managerId)
                    ->values()
                    ->all();

                $poolCount = count($pool);
                $maxMembers = min(6, $poolCount);
                $minMembers = min(3, $maxMembers);
                $memberCount = rand($minMembers, $maxMembers);
                $selected = array_slice($pool, 0, $memberCount);

                foreach ($selected as $userId) {
                    GroupMember::firstOrCreate(
                        ['group_id' => $group->id, 'user_id' => $userId],
                        [
                            'added_by' => $selected[array_rand($selected)],
                            'joined_at' => now()->subDays(rand(1, 60)),
                        ]
                    );
                }
            }
        }
    }
}