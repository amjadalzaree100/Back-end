<?php

namespace App\Services;

use App\Models\VerificationCode;
use App\Models\User;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class VerificationCodeService
{
    public function generateAndSend(string $email, string $username): void
    {
        VerificationCode::where('email', $email)->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        VerificationCode::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
        ]);

        Mail::to($email)->send(new VerificationCodeMail($username, $code));
    }

    public function verify(string $email, string $code): bool
    {
        $record = VerificationCode::where('email', $email)
            ->where('code', $code)
            ->where('is_used', false)
            ->first();

        if (!$record || !$record->isValid()) {
            return false;
        }

        DB::transaction(function () use ($record) {
            $record->update(['is_used' => true]);
            $user = User::where('email', $record->email)->first();
            if ($user && !$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        });

        return true;
    }
    // Resend verification code only if last code was sent more than 1 minute ago.
    public function resendVerificationCode(string $email, string $username): bool
    {
        $lastCode = VerificationCode::where('email', $email)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastCode && $lastCode->created_at->diffInSeconds(now()) < 60) {
            return false;
        }

        $this->generateAndSend($email, $username);
        return true;
    }

}
