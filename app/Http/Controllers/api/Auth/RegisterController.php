<?php

namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\api\Auth\RegisterRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\AuthService;
use App\Services\VerificationCodeService;

class RegisterController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected AuthService $authService,
        protected VerificationCodeService $verificationService
    ) {}


    public function register(RegisterRequest $request)
    {
        $user = $this->authService->register($request->validated());

        // $this->verificationService->generateAndSend($user->email, $user->name);

        return $this->successResponse([
            'email' => $user->email,
            'requires_verification' => true
        ], 'Account created successfully. Please verify your email to login.', 201);
    }
}
