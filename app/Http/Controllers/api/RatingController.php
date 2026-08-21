<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rating\StoreRatingRequest;
use App\Models\Project;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RatingController extends Controller
{
    /**
     * Rate a project member (only the project owner can rate).
     * Rating a member again in the same project updates the previous rating.
     */
    public function rate(StoreRatingRequest $request, Project $project): JsonResponse
    {
        try {
            $currentUser = $request->user();

            if (!$project->isOwner($currentUser->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the project owner can rate project members.',
                ], 403);
            }

            $ratedUserId = (int) $request->rated_user_id;

            if ($currentUser->id === $ratedUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot rate yourself.',
                ], 422);
            }

            if (!$project->hasUser($ratedUserId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The user is not a member of this project.',
                ], 422);
            }

            DB::beginTransaction();

            $rating = Rating::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'rater_id' => $currentUser->id,
                    'rated_user_id' => $ratedUserId,
                ],
                [
                    'rating' => (int) $request->rating,
                ]
            );

            DB::commit();

            $ratedUser = User::find($ratedUserId);

            return response()->json([
                'success' => true,
                'message' => 'Rating saved successfully.',
                'data' => [
                    'rating_id' => $rating->id,
                    'rater_id' => $currentUser->id,
                    'rated_user_id' => $ratedUserId,
                    'project_id' => $project->id,
                    'rating' => (int) $rating->rating,
                    'ratings_count' => $ratedUser->ratings_count,
                    'ratings_average' => $ratedUser->ratings_average,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Rating failed: ' . $e->getMessage(), [
                'rater_id' => $request->user()->id,
                'project_id' => $project->id,
                'rated_user_id' => $request->rated_user_id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving the rating. Please try again later.',
            ], 500);
        }
    }

    /**
     * Return rating stats (count + average) for a user, plus the breakdown.
     * Works regardless of whether the user's account is public or private.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        try {
            $stats = Rating::where('rated_user_id', $user->id)
                ->selectRaw('COUNT(*) as total, AVG(rating) as average')
                ->first();

            $total = (int) ($stats->total ?? 0);
            $average = $total > 0 ? round((float) $stats->average, 2) : 0.0;

            $breakdown = Rating::where('rated_user_id', $user->id)
                ->select('rating', DB::raw('COUNT(*) as count'))
                ->groupBy('rating')
                ->pluck('count', 'rating')
                ->map(fn ($count) => (int) $count)
                ->toArray();

            $distribution = [];
            for ($star = 1; $star <= 5; $star++) {
                $distribution[$star] = $breakdown[$star] ?? 0;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'ratings_count' => $total,
                    'ratings_average' => $average,
                    'distribution' => $distribution,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching ratings failed: ' . $e->getMessage(), [
                'rated_user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the ratings. Please try again later.',
            ], 500);
        }
    }

}
