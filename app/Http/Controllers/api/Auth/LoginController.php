<?php

namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\api\Auth\DeleteAccountRequest;
use App\Http\Requests\api\Auth\LoginRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\AccountDeletionService;
use App\Services\AuthService;
use App\Services\VerificationCodeService;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected AuthService $authService, protected VerificationCodeService $verificationService) {}

    public function login(LoginRequest $request)
    {
        $field = $request->getLoginField();
        $value = $request->input('login');

        $user = $this->authService->login(
            $field,
            $value,
            $request->password,
            $request->boolean('remember')
        );

        if (!$user->hasVerifiedEmail()) {
            $this->verificationService->resendVerificationCode($user->email, $user->name);

            return $this->errorResponse(
                'Email not verified. A new verification code has been sent to your email.',
                403
            );
        }

        if (!$user->is_active) {
            return $this->errorResponse(
                'Account is deactivated. Please contact support.',
                403
            );
        }

        $token = $user->createToken('mobile_app')->plainTextToken;

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ], 'Login successful.');
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user->is_active) {
            return $this->errorResponse('Your account is deactivated. Please contact support.', 403);
        }


        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'email_verified_at' => $user->email_verified_at
        ]);
    }

    public function destroy(DeleteAccountRequest $request, AccountDeletionService $service)
    {
        $service->deleteAccount($request->user(), $request->password);

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }
}
