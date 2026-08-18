<?php

namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\api\Auth\ForgotPasswordRequest;
use App\Http\Requests\api\Auth\ResetPasswordRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordCodeMail;
use App\Mail\PasswordChangedMail;

class PasswordResetController extends Controller
{
    use ApiResponseTrait;

    public function forgot(ForgotPasswordRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->is_active) {
            return $this->successResponse(null, 'If your email is registered, you will receive a reset code.');
        }

        DB::table('password_reset_codes')->where('email', $user->email)->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_codes')->insert([
            'email' => $user->email,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Mail::to($user->email)->send(new ResetPasswordCodeMail($user->name, $code));

        return $this->successResponse(null, 'Password reset code sent to your email.');
    }

    public function reset(ResetPasswordRequest $request)
    {
        $resetRecord = DB::table('password_reset_codes')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return $this->errorResponse('Invalid reset code.', 400);
        }

        if (!Hash::check($request->code, $resetRecord->code)) {
            return $this->errorResponse('Invalid reset code.', 400);
        }

        if (now()->gt($resetRecord->expires_at)) {
            DB::table('password_reset_codes')->where('email', $request->email)->delete();
            return $this->errorResponse('Reset code has expired. Please request a new one.', 400);
        }

        if ($resetRecord->is_used) {
            return $this->errorResponse('This code has already been used.', 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        Mail::to($user->email)->send(new PasswordChangedMail($user->name, request()->ip()));

        DB::table('password_reset_codes')
            ->where('email', $request->email)
            ->update(['is_used' => true]);

        $user->tokens()->delete();

        return $this->successResponse(null, 'Password reset successfully. Please login with your new password.');
    }
}
