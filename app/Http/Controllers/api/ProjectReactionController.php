<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectReaction\StoreProjectReactionRequest;
use App\Models\Project;
use App\Models\ProjectReaction;
use Illuminate\Http\JsonResponse;

class ProjectReactionController extends Controller
{
    public function toggleReaction(StoreProjectReactionRequest $request, Project $project): JsonResponse
    {
        if (!$project->allow_reactions) {
            return response()->json([
                'success' => false,
                'message' => 'Reactions are disabled for this project.'
            ], 403);
        }
        $userId = $request->user()->id;
        $newReaction = $request->reaction_type;

        $existing = ProjectReaction::where('project_id', $project->id)
            ->where('user_id', $userId)
            ->first();

        if (!$existing) {
            ProjectReaction::create([
                'project_id' => $project->id,
                'user_id' => $userId,
                'reaction_type' => $newReaction,
            ]);
            $message = 'Reaction added';
        } elseif ($existing->reaction_type === $newReaction) {
            $existing->delete();
            $message = 'Reaction removed';
        } else {
            $existing->update(['reaction_type' => $newReaction]);
            $message = 'Reaction updated';
        }

        $project->load('reactions');
        $counts = $project->reaction_counts;
        $userReaction = $project->user_reaction;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'reaction_counts' => $counts,
                'user_reaction' => $userReaction,
            ]
        ]);
    }

    public function getProjectReactions(Project $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'reaction_counts' => $project->reaction_counts,
                'user_reaction' => $project->user_reaction,
            ]
        ]);
    }
}
