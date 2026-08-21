<?php

namespace Database\Factories;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FcmToken>
 *
 * Note: the `fake()` helper from Laravel is not available in this project
 * (the `fakerphp/faker` package installed here is a provider-only build
 * without a Generator/Factory class), so this factory uses a static
 * counter and Str:: helpers instead.
 */
class FcmTokenFactory extends Factory
{
    protected $model = FcmToken::class;

    public function definition(): array
    {
        static $i = 0;
        $i++;

        $deviceTypes = ['web', 'android', 'ios'];
        $deviceNames = [
            'web' => ['Windows - Chrome', 'macOS - Safari', 'Linux - Firefox', 'Windows - Edge'],
            'android' => ['Samsung Galaxy S24', 'Google Pixel 8', 'Xiaomi Redmi Note 13', 'OnePlus 12'],
            'ios' => ['iPhone 15 Pro', 'iPhone 14', 'iPad Air 5', 'iPhone 15'],
        ];

        $deviceType = $deviceTypes[($i - 1) % count($deviceTypes)];
        $devices = $deviceNames[$deviceType];
        $deviceName = $devices[($i - 1) % count($devices)];

        $token = Str::random(8).':APA91b'
            .Str::random(40).'_'.Str::random(30)
            .'-'.Str::random(30).'_'.Str::random(40);

        return [
            'user_id' => User::factory(),
            'token' => $token.'_'.$i,
            'device_type' => $deviceType,
            'device_name' => $deviceName,
            'last_used_at' => rand(0, 1) ? now()->subDays(rand(0, 14)) : null,
        ];
    }

    public function web(): static
    {
        return $this->state(fn () => ['device_type' => 'web']);
    }

    public function android(): static
    {
        return $this->state(fn () => ['device_type' => 'android']);
    }

    public function ios(): static
    {
        return $this->state(fn () => ['device_type' => 'ios']);
    }
}