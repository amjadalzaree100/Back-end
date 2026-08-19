<?php

namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;



class LogoutController extends Controller
{
    use ApiResponseTrait;

    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $user->currentAccessToken()->delete();

        Log::info('User logged out.', ['user_id' => $user->id]);

        return $this->successResponse(null, 'Logged out successfully.');
    }

    public function logoutFromAllDevices(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $user->tokens()->delete();

        Log::info('User logged out from all devices.', ['user_id' => $user->id]);

        return $this->successResponse(null, 'Logged out from all devices successfully.');
    }

    public function devices(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $tokens = $user->tokens()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('last_used_at')                     
            ->get()
            ->map(function ($token) use ($request) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toISOString(),
                    'created_at' => $token->created_at->toISOString(),
                    'is_current' => $token->id === $request->user()->currentAccessToken()->id,
                ];
            });

        return $this->successResponse($tokens, 'Active devices retrieved.');
    }

    public function logoutDevice(Request $request, $tokenId)
    {
        $token = $request->user()->tokens()->findOrFail($tokenId);

        if ($token->id === $request->user()->currentAccessToken()->id) {
            return $this->errorResponse('Cannot delete current token. Use logout instead.', 422);
        }

        $token->delete();

        return $this->successResponse(null, 'Logged out from device successfully.');
    }

    public function logoutOtherDevices(Request $request)
    {
        $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
        return $this->successResponse(null, 'Logged out from all other devices.');
    }


}