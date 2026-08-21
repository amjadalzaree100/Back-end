<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $reports = [
            [
                'reporter' => 'alaa.gbh0@gmail.com',
                'reported' => 'ahmed.khalid@example.com',
                'reason' => 'Inappropriate behavior in comments',
                'details' => 'The user repeatedly posts condescending remarks aimed at other members in the project comment sections. Several people have reported feeling intimidated during discussions. This behavior has been going on for over a week without any improvement.',
                'status' => 'open',
                'days_ago' => 1,
            ],
            [
                'reporter' => 'alaa.gbh0@gmail.com',
                'reported' => 'youssef.ali@example.com',
                'reason' => 'Spam messages',
                'details' => 'The account keeps sending the same promotional messages to multiple users through direct messages. The content links to an external service that is unrelated to any project on the platform. I have received the identical message four times in the last few days.',
                'status' => 'open',
                'days_ago' => 2,
            ],
            [
                'reporter' => 'sara.mohamed@example.com',
                'reported' => 'kareem.adel@example.com',
                'reason' => 'Harassment',
                'details' => 'The user has sent multiple threatening messages that made me feel unsafe. They continue to contact me even after I asked them to stop. This has escalated beyond a normal disagreement into persistent harassment.',
                'status' => 'reviewed',
                'days_ago' => 9,
            ],
            [
                'reporter' => 'omar.hassan@example.com',
                'reported' => 'fatima.zahra@example.com',
                'reason' => 'Offensive language',
                'details' => 'The user used vulgar and insulting language toward me in a public project thread. The messages contained language that is completely unacceptable in a professional workspace. Other members witnessed the exchange and were equally shocked.',
                'status' => 'open',
                'days_ago' => 3,
            ],
            [
                'reporter' => 'layla.abbas@example.com',
                'reported' => 'tariq.samir@example.com',
                'reason' => 'Fake account',
                'details' => 'This account appears to be a fake profile using a stolen name and photos. The profile has no real activity and only exists to follow and message people. I believe it is being used for identity fraud.',
                'status' => 'reviewed',
                'days_ago' => 12,
            ],
            [
                'reporter' => 'ahmed.khalid@example.com',
                'reported' => 'alaa.gbh0@gmail.com',
                'reason' => 'Unprofessional conduct',
                'details' => 'The user behaved unprofessionally during a project disagreement, resorting to personal attacks. They insulted team members and refused to engage in constructive discussion. This created a hostile environment for everyone involved.',
                'status' => 'dismissed',
                'days_ago' => 25,
            ],
            [
                'reporter' => 'nour.mahmoud@example.com',
                'reported' => 'ahmed.khalid@example.com',
                'reason' => 'Spam messages',
                'details' => 'I keep receiving unsolicited advertising messages from this account. The messages contain links to a website that is unrelated to the platform. I have blocked the account but the messages keep arriving.',
                'status' => 'open',
                'days_ago' => 4,
            ],
            [
                'reporter' => 'kareem.adel@example.com',
                'reported' => 'sara.mohamed@example.com',
                'reason' => 'Harassment',
                'details' => 'The user has been following my posts and commenting negatively on everything I share. They have sent me private messages with aggressive and demeaning content. This has become a pattern of targeted harassment.',
                'status' => 'reviewed',
                'days_ago' => 15,
            ],
            [
                'reporter' => 'fatima.zahra@example.com',
                'reported' => 'omar.hassan@example.com',
                'reason' => 'Offensive language',
                'details' => 'The user used offensive and abusive language in a group discussion. The words used were degrading and completely uncalled for. This is not the first time the user has behaved this way.',
                'status' => 'open',
                'days_ago' => 6,
            ],
            [
                'reporter' => 'tariq.samir@example.com',
                'reported' => 'nour.mahmoud@example.com',
                'reason' => 'Inappropriate behavior in comments',
                'details' => 'The user consistently derails comment threads with irrelevant and hostile content. They have also posted the personal details of another member without consent. This behavior is disruptive to the entire community.',
                'status' => 'reviewed',
                'days_ago' => 11,
            ],
        ];

        foreach ($reports as $report) {
            $reporter = User::where('email', $report['reporter'])->first();
            $reported = User::where('email', $report['reported'])->first();

            if (! $reporter || ! $reported || $reporter->id === $reported->id) {
                continue;
            }

            $createdAt = Carbon::now()->subDays($report['days_ago'])->subHours(rand(0, 12));

            Report::firstOrCreate(
                [
                    'reporter_id' => $reporter->id,
                    'reported_user_id' => $reported->id,
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