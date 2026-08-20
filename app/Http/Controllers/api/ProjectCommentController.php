<?php

namespace App\Http\Controllers\api;

use App\Events\ProjectNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectComment\StoreProjectCommentRequest;
use App\Http\Requests\ProjectComment\UpdateProjectCommentRequest;
use App\Http\Resources\ProjectCommentResource;
use App\Models\Project;
use App\Models\ProjectComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectCommentController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        if (!$this->canAccessProjectComments($request, $project)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this project comments',
            ], 403);
        }

        if (!$project->allow_commit) {
        return response()->json([
                'success' => false,
                'message' => 'Comments are disabled for this project.'
            ], 403);
        }


        $comments = $project->projectComments()
            ->with(['user', 'user.profile', 'replies.user', 'replies.user.profile'])
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProjectCommentResource::collection($comments),
            'total' => $comments->count(),
        ]);
    }
    public function store(StoreProjectCommentRequest $request, Project $project): JsonResponse
    {
        if (!$this->canAccessProjectComments($request, $project)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this project comments',
            ], 403);
        }

        if (!$project->allow_commit) {
        return response()->json([
                'success' => false,
                'message' => 'Comments are disabled for this project.'
            ], 403);
        }

        $validated = $request->validated();

        if (!empty($validated['parent_id'])) {
            $parentBelongsToProject = $project->projectComments()
                ->where('id', $validated['parent_id'])
                ->exists();

            if (!$parentBelongsToProject) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent comment does not belong to this project',
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $comment = $project->projectComments()->create([
                'user_id' => $request->user()->id,
                'content' => $validated['content'],
                'parent_id' => $validated['parent_id'] ?? null,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage()
            ], 500);
        }

        $comment->load(['user', 'user.profile']);

        $userId = $request->user()->id;
        if ($project->created_by !== $userId) {
            ProjectNotificationEvent::dispatch(
                userIds: [$project->created_by],
                scenario: 'project_commented',
                project: $project,
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'data' => new ProjectCommentResource($comment)
        ], 201);
    }

    public function show(Request $request, Project $project, ProjectComment $comment): JsonResponse
    {
        if ($comment->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment does not belong to this project'
            ], 404);
        }

        if (!$this->canAccessProjectComments($request, $project)) {
            return response()->json([
                'success' => false,
                'message' => 'This project is not public'
            ], 403);
        }

        $comment->load(['user', 'user.profile', 'replies.user', 'replies.user.profile']);

        return response()->json([
            'success' => true,
            'data' => new ProjectCommentResource($comment)
        ]);
    }

    public function update(UpdateProjectCommentRequest $request, Project $project, ProjectComment $comment): JsonResponse
    {
        if ($comment->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment does not belong to this project'
            ], 404);
        }

        $user = $request->user();
        $isOwner = $comment->user_id === $user->id;
        $isProjectOwner = $project->created_by === $user->id;

        if (!$isOwner && !$isProjectOwner) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own comments'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $comment->update(['content' => $request->validated()['content']]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => new ProjectCommentResource($comment->load(['user', 'user.profile']))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, Project $project, ProjectComment $comment): JsonResponse
    {
        if ($comment->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Comment does not belong to this project'
            ], 404);
        }

        $user = $request->user();
        $isOwner = $comment->user_id === $user->id;
        $isProjectOwner = $project->created_by === $user->id;

        if (!$isOwner && !$isProjectOwner) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this comment'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $comment->replies()->delete();
            $comment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function canAccessProjectComments(Request $request, Project $project): bool
    {
        $userId = $request->user()?->id;

        if (!$userId) {
            return false;
        }

        if ($project->visibility === 'public') {
            return true;
        }

        return $project->isOwner($userId) || $project->hasUser($userId);
    }
}
