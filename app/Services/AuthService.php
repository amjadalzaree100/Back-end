<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => null,
            'is_active' => true,
        ]);
    }

    public function login(string $field, string $value, string $password, bool $remember = false): ?User
    {
        $user = User::where($field, $value)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'login' => ['User not found.']
            ]);
        }

        if (!Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password.']
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Your account is deactivated. Please contact support.']
            ]);
        }

        return $user;
    }
}
