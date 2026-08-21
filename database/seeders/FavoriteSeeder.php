<?php

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        $alaa = $users->firstWhere('email', 'alaa.gbh0@gmail.com') ?? $users->first();

        $allUserIds = $users->pluck('id')->toArray();

        $total = 0;

        foreach ($users as $user) {
            $isAlaa = $user->id === $alaa->id;

            // alaa favorites 3-4 users, everyone else favorites 2-3 users.
            $count = $isAlaa ? rand(3, 4) : rand(2, 3);

            $candidates = array_values(array_diff($allUserIds, [$user->id]));
            $favorited = 0;

            while ($favorited < $count && ! empty($candidates) && $total < 30) {
                $target = $candidates[array_rand($candidates)];
                $candidates = array_values(array_diff($candidates, [$target]));

                // Respect the unique constraint on (user_id, favorite_user_id).
                if (Favorite::where('user_id', $user->id)
                    ->where('favorite_user_id', $target)
                    ->exists()) {
                    continue;
                }

                Favorite::factory()->create([
                    'user_id' => $user->id,
                    'favorite_user_id' => $target,
                ]);

                $favorited++;
                $total++;
            }
        }
    }
}