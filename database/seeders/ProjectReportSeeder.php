<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProjectReportSeeder extends Seeder
{
    public function run(): void
    {
        $projectReports = [
            [
                'reporter' => 'ahmed.khalid@example.com',
                'project' => 'Website Redesign',
                'reason' => 'Spam project',
                'details' => 'This project looks like it was created just to advertise an external service. The description contains links and promotional text that are unrelated to genuine project work. There is no real activity or legitimate content behind it.',
                'status' => 'open',
                'days_ago' => 2,
            ],
            [
                'reporter' => 'sara.mohamed@example.com',
                'project' => 'Mobile App Development',
                'reason' => 'Misleading project description',
                'details' => 'The project description promises a fully featured mobile application, but the actual repository contains only placeholder files. Members who joined based on the description have found the scope completely different. This feels intentionally misleading.',
                'status' => 'open',
                'days_ago' => 5,
            ],
            [
                'reporter' => 'youssef.ali@example.com',
                'project' => 'Database Migration',
                'reason' => 'Abandoned project',
                'details' => 'The project has had no commits, updates, or member activity for several months. The owner has not responded to any messages about the project status. This project appears to have been abandoned.',
                'status' => 'reviewed',
                'days_ago' => 14,
            ],
            [
                'reporter' => 'nour.mahmoud@example.com',
                'project' => 'API Integration',
                'reason' => 'Violates terms of service',
                'details' => 'This project collects personal information from users without any clear privacy policy. The terms of service require users to consent to data sharing that the platform explicitly forbids. This violates the platform terms of service.',
                'status' => 'open',
                'days_ago' => 3,
            ],
            [
                'reporter' => 'tariq.samir@example.com',
                'project' => 'Internal Dashboard',
                'reason' => 'Project contains inappropriate content',
                'details' => 'The project description contains offensive material that is inappropriate for the platform. The content appears to have been uploaded without any review. This kind of content is not acceptable in a professional workspace.',
                'status' => 'open',
                'days_ago' => 7,
            ],
            [
                'reporter' => 'omar.hassan@example.com',
                'project' => 'API Integration',
                'reason' => 'Misleading project description',
                'details' => 'The project is described as a full payment gateway integration, but it only references a single test endpoint. Members were expecting complete integration documentation and examples. The description does not match the actual deliverable.',
                'status' => 'reviewed',
                'days_ago' => 16,
            ],
            [
                'reporter' => 'kareem.adel@example.com',
                'project' => 'Database Migration',
                'reason' => 'Abandoned project',
                'details' => 'The project has not been maintained since it was created several months ago. Tasks and requests from members have gone completely unanswered. The owner should either archive it or hand it over to an active team.',
                'status' => 'dismissed',
                'days_ago' => 28,
            ],
        ];

        foreach ($projectReports as $report) {
            $reporter = User::where('email', $report['reporter'])->first();
            $project = Project::where('name', $report['project'])->first();

            if (! $reporter || ! $project) {
                continue;
            }

            $createdAt = Carbon::now()->subDays($report['days_ago'])->subHours(rand(0, 12));

            ProjectReport::firstOrCreate(
                [
                    'reporter_id' => $reporter->id,
                    'reported_project_id' => $project->id,
                ],
                [
                    'reason' => $report['reason'],
                    'details' => $report['details'],
                    'status' => $report['status'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }
    }
}