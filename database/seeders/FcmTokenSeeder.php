<?php

namespace Database\Seeders;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FcmTokenSeeder extends Seeder
{
    public function run(): void
    {
        $tokens = [
            ['user' => 'alaa.gbh0@gmail.com', 'device_type' => 'web', 'device_name' => 'Chrome on Windows'],
            ['user' => 'alaa.gbh0@gmail.com', 'device_type' => 'android', 'device_name' => 'Samsung Galaxy S21'],
            ['user' => 'ahmed.khalid@example.com', 'device_type' => 'android', 'device_name' => 'Pixel 6'],
            ['user' => 'sara.mohamed@example.com', 'device_type' => 'ios', 'device_name' => 'iPhone 13'],
            ['user' => 'sara.mohamed@example.com', 'device_type' => 'web', 'device_name' => 'Firefox on Linux'],
            ['user' => 'omar.hassan@example.com', 'device_type' => 'web', 'device_name' => 'Safari on macOS'],
            ['user' => 'layla.abbas@example.com', 'device_type' => 'android', 'device_name' => 'OnePlus 9'],
            ['user' => 'layla.abbas@example.com', 'device_type' => 'web', 'device_name' => 'Chrome on Windows'],
            ['user' => 'youssef.ali@example.com', 'device_type' => 'ios', 'device_name' => 'iPad Pro'],
            ['user' => 'nour.mahmoud@example.com', 'device_type' => 'android', 'device_name' => 'Pixel 6'],
            ['user' => 'nour.mahmoud@example.com', 'device_type' => 'web', 'device_name' => 'Firefox on Linux'],
            ['user' => 'kareem.adel@example.com', 'device_type' => 'web', 'device_name' => 'Safari on macOS'],
            ['user' => 'fatima.zahra@example.com', 'device_type' => 'android', 'device_name' => 'Samsung Galaxy S21'],
            ['user' => 'tariq.samir@example.com', 'device_type' => 'web', 'device_name' => 'Chrome on Windows'],
        ];

        foreach ($tokens as $tokenData) {
            $user = User::where('email', $tokenData['user'])->first();

            if (! $user) {
                continue;
            }

            // Standard FCM registration token: "xxxx:APA91b" prefix followed by
            // an alphanumeric payload (~150-160 characters in total).
            $token = Str::random(4).':APA91b'.Str::random(rand(139, 149));

            FcmToken::create([
                'user_id' => $user->id,
                'token' => $token,
                'device_type' => $tokenData['device_type'],
                'device_name' => $tokenData['device_name'],
                'last_used_at' => Carbon::now()->subDays(rand(0, 29))->subHours(rand(0, 23)),
            ]);
        }
    }
}