<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FavoriteUser\AddFavoriteRequest;
use App\Http\Resources\FavoriteResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favorites()
            ->with('favoriteUser.profile')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => FavoriteResource::collection($favorites),
            'total' => $favorites->count(),
        ]);
    }

    public function store(AddFavoriteRequest $request): JsonResponse
    {
        $user = $request->user();
        $favoriteUser = User::findOrFail($request->user_id);

        if ($user->addToFavorites($favoriteUser)) {
            return response()->json([
                'success' => true,
                'message' => 'User added to favorites successfully',
                'data' => [
                    'user_id' => $favoriteUser->id,
                    'name' => $favoriteUser->name,
                ],
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'User already in favorites or invalid operation',
        ], 400);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $favoriteUser = User::findOrFail($userId);

        if ($user->removeFromFavorites($favoriteUser)) {
            return response()->json([
                'success' => true,
                'message' => 'User removed from favorites successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'User not found in favorites',
        ], 404);
    }

    public function check(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'is_favorite' => $user->isFavorite($targetUser),
            ],
        ]);
    }
    public function toggle(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($userId);

        if ($user->id === $targetUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot add yourself to favorites'
            ], 400);
        }

        $isFavorite = $user->isFavorite($targetUser);

        if ($isFavorite) {
            $user->removeFromFavorites($targetUser);
            return response()->json([
                'success' => true,
                'action' => 'removed',
                'message' => 'User removed from favorites',
                'is_favorite' => false
            ]);
        } else {
            $user->addToFavorites($targetUser);
            return response()->json([
                'success' => true,
                'action' => 'added',
                'message' => 'User added to favorites',
                'is_favorite' => true
            ]);
        }
    }
}
