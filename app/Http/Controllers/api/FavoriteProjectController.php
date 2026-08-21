<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FavoriteProject\AddFavoriteProjectRequest;
use App\Http\Resources\FavoriteProjectResource;
use App\Models\FavoriteProject;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = FavoriteProject::where('user_id', $request->user()->id)
            ->whereHas('project')
            ->with('project.creator')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => FavoriteProjectResource::collection($favorites),
            'total' => $favorites->count(),
        ]);
    }

    public function store(AddFavoriteProjectRequest $request): JsonResponse
    {
        $user = $request->user();
        $project = Project::findOrFail($request->project_id);

        if ($user->addProjectToFavorites($project)) {
            return response()->json([
                'success' => true,
                'message' => 'Project added to favorites successfully',
                'data' => [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                ],
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'Project already in favorites',
        ], 400);
    }

    public function destroy(Request $request, int $projectId): JsonResponse
    {
        $user = $request->user();
        $project = Project::findOrFail($projectId);

        if ($user->removeProjectFromFavorites($project)) {
            return response()->json([
                'success' => true,
                'message' => 'Project removed from favorites successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Project not found in favorites',
        ], 404);
    }

    public function check(Request $request, int $projectId): JsonResponse
    {
        $user = $request->user();
        $project = Project::findOrFail($projectId);

        return response()->json([
            'success' => true,
            'data' => [
                'is_favorite' => $user->isProjectFavorite($project),
            ],
        ]);
    }

    public function toggle(Request $request, int $projectId): JsonResponse
    {
        $user = $request->user();
        $project = Project::findOrFail($projectId);

        $isFavorite = $user->isProjectFavorite($project);

        if ($isFavorite) {
            $user->removeProjectFromFavorites($project);
            return response()->json([
                'success' => true,
                'action' => 'removed',
                'message' => 'Project removed from favorites',
                'is_favorite' => false,
            ]);
        } else {
            $user->addProjectToFavorites($project);
            return response()->json([
                'success' => true,
                'action' => 'added',
                'message' => 'Project added to favorites',
                'is_favorite' => true,
            ]);
        }
    }
}
