<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class RequestSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::with('users')->get();

        if ($projects->isEmpty()) {
            return;
        }

        $allUserIds = User::pluck('id')->toArray();

        $joinMessages = [
            "I'd like to contribute to this project",
            'Can I join the team?',
            'I would love to help out with this project',
            'Your project looks interesting, could I get involved?',
            'I have relevant experience and would like to join',
            'Please accept my request to join',
        ];

        $inviteMessages = [
            'Your skills would be valuable here',
            "We're looking for more developers",
            'We would love to have you on the team',
            'I think you would be a great fit for this project',
            'Would you be interested in joining our project?',
            'We need your expertise on this one',
        ];

        $roles = ['manager', 'user', 'observer'];

        $targetTotal = rand(15, 25);
        $created = 0;

        while ($created < $targetTotal) {
            $project = $projects->random();
            $members = $project->users->pluck('id')->toArray();

            if (empty($members)) {
                continue;
            }

            // Managers = owners + managers from the project_users pivot.
            $managers = $project->users
                ->filter(fn ($user) => in_array($user->pivot->role, ['owner', 'manager']))
                ->pluck('id')
                ->values()
                ->toArray();

            if (empty($managers)) {
                $managers = [$project->created_by];
            }

            $nonMembers = array_values(array_diff($allUserIds, $members));
            if (empty($nonMembers)) {
                continue;
            }

            $type = rand(0, 1) ? 'join_request' : 'invitation';

            if ($type === 'join_request') {
                $senderId = $nonMembers[array_rand($nonMembers)];
                $receiverId = $managers[array_rand($managers)];
                $message = $joinMessages[array_rand($joinMessages)];
                $role = null;
            } else {
                $senderId = $managers[array_rand($managers)];
                $receiverId = $nonMembers[array_rand($nonMembers)];
                $message = $inviteMessages[array_rand($inviteMessages)];
                $role = $roles[array_rand($roles)];
            }

            // Status distribution: ~40% pending, ~45% approved, ~15% rejected.
            $statusRoll = rand(1, 100);
            if ($statusRoll <= 40) {
                $status = 'pending';
                $respondedAt = null;
                $respondedBy = null;
                $createdAt = Carbon::now()->subDays(rand(0, 7))->subHours(rand(0, 23));
            } else {
                $status = $statusRoll <= 85 ? 'approved' : 'rejected';
                $createdAt = Carbon::now()->subDays(rand(3, 30))->subHours(rand(0, 23));
                $respondedAt = (clone $createdAt)->addDays(rand(1, 3))->addHours(rand(0, 23));
                if ($respondedAt->gt(Carbon::now())) {
                    $respondedAt = Carbon::now()->subHours(rand(1, 24));
                }
                $respondedBy = $managers[array_rand($managers)];
            }

            Request::create([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'project_id' => $project->id,
                'type' => $type,
                'status' => $status,
                'message' => $message,
                'role' => $role,
                'responded_at' => $respondedAt,
                'responded_by' => $respondedBy,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $created++;
        }
    }
}