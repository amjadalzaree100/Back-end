<?php

namespace Database\Seeders;

use App\Models\BlockedUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BlockedUserSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            [
                'user' => 'alaa.gbh0@gmail.com',
                'blocked' => 'ahmed.khalid@example.com',
                'reason' => 'Spam',
                'days_ago' => 18,
            ],
            [
                'user' => 'alaa.gbh0@gmail.com',
                'blocked' => 'youssef.ali@example.com',
                'reason' => 'Harassment',
                'days_ago' => 22,
            ],
            [
                'user' => 'sara.mohamed@example.com',
                'blocked' => 'kareem.adel@example.com',
                'reason' => 'Inappropriate messages',
                'days_ago' => 9,
            ],
            [
                'user' => 'omar.hassan@example.com',
                'blocked' => 'fatima.zahra@example.com',
                'reason' => null,
                'days_ago' => 5,
            ],
            [
                'user' => 'layla.abbas@example.com',
                'blocked' => 'tariq.samir@example.com',
                'reason' => 'Personal conflict',
                'days_ago' => 13,
            ],
            [
                'user' => 'ahmed.khalid@example.com',
                'blocked' => 'alaa.gbh0@gmail.com',
                'reason' => null,
                'days_ago' => 2,
            ],
        ];

        foreach ($blocks as $block) {
            $user = User::where('email', $block['user'])->first();
            $blockedUser = User::where('email', $block['blocked'])->first();

            if (! $user || ! $blockedUser || $user->id === $blockedUser->id) {
                continue;
            }

            $createdAt = Carbon::now()->subDays($block['days_ago'])->subHours(rand(0, 12));

            BlockedUser::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'blocked_user_id' => $blockedUser->id,
                ],
                [
                    'reason' => $block['reason'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }
    }
}