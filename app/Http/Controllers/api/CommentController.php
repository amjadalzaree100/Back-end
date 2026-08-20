<?php

namespace App\Http\Controllers\api;

use App\Events\TaskNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\ProjectComment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function index(Request $request, Task $task): JsonResponse
    {
        $this->checkTaskAccess($request, $task);


        $comments = $task->comments()
            ->with(['user', 'user.profile'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => CommentResource::collection($comments),
            'total' => $comments->count(),
        ]);
    }

    public function myComments(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $taskComments = Comment::with(['user', 'user.profile', 'task', 'task.project'])
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'type' => 'task',
                'content' => $comment->content,
                'task_id' => $comment->task_id,
                'task_title' => $comment->task?->title,
                'project_id' => $comment->task?->project_id,
                'project_name' => $comment->task?->project?->name,
                'parent_id' => null,
                'user' => [
                    'id' => $comment->user->id ?? null,
                    'name' => $comment->user->name ?? 'Unknown',
                    'avatar' => $comment->user->profile?->avatar ?? null,
                ],
                'created_at' => $comment->created_at?->toISOString(),
                'updated_at' => $comment->updated_at?->toISOString(),
                'created_at_human' => $comment->created_at?->diffForHumans(),
            ]);

        $projectComments = ProjectComment::with(['user', 'user.profile', 'project'])
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (ProjectComment $comment) => [
                'id' => $comment->id,
                'type' => 'project',
                'content' => $comment->content,
                'task_id' => null,
                'task_title' => null,
                'project_id' => $comment->project_id,
                'project_name' => $comment->project?->name,
                'parent_id' => $comment->parent_id,
                'user' => [
                    'id' => $comment->user->id ?? null,
                    'name' => $comment->user->name ?? 'Unknown',
                    'avatar' => $comment->user->profile?->avatar ?? null,
                ],
                'created_at' => $comment->created_at?->toISOString(),
                'updated_at' => $comment->updated_at?->toISOString(),
                'created_at_human' => $comment->created_at?->diffForHumans(),
            ]);

        $comments = $taskComments
            ->concat($projectComments)
            ->sortByDesc('created_at')
            ->values();

        $page = max((int) $request->query('page', 1), 1);
        $perPage = max((int) $request->query('per_page', 20), 1);
        $total = $comments->count();

        $paginator = new LengthAwarePaginator(
            $comments->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $this->checkTaskAccess($request, $task);

        try {
            DB::beginTransaction();

            $comment = $task->comments()->create([
                'user_id' => $request->user()->id,
                'content' => $request->validated()['content'],
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage(),
            ], 500);
        }

        $comment->load(['user', 'user.profile']);

        $userId = $request->user()->id;

        $userIds = [];
        if ($task->created_by && $task->created_by !== $userId) {
            $userIds[] = $task->created_by;
        }
        if ($task->assigned_to && $task->assigned_to !== $userId) {
            $userIds[] = $task->assigned_to;
        }

        $userIds = array_unique($userIds);

        if (!empty($userIds)) {
            TaskNotificationEvent::dispatch(
                userIds: $userIds,
                scenario: 'commented',
                task: $task,
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'data' => new CommentResource($comment),
        ], 201);
    }

    public function show(Request $request, Task $task, int $commentId): JsonResponse
    {
        $this->checkTaskAccess($request, $task);

        $comment = Comment::with(['user', 'user.profile'])
            ->where('id', $commentId)
            ->where('task_id', $task->id)
            ->first();

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found or does not belong to this task',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new CommentResource($comment),
        ]);
    }

    public function update(UpdateCommentRequest $request, Task $task, int $commentId): JsonResponse
    {
        $this->checkTaskAccess($request, $task);

        $comment = Comment::where('id', $commentId)
            ->where('task_id', $task->id)
            ->first();

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found or does not belong to this task',
            ], 404);
        }

        if ($comment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own comments',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $comment->update([
                'content' => $request->validated()['content'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => new CommentResource($comment->load(['user', 'user.profile'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, Task $task, int $commentId): JsonResponse
    {
        $this->checkTaskAccess($request, $task);

        $comment = Comment::where('id', $commentId)
            ->where('task_id', $task->id)
            ->first();

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found or does not belong to this task',
            ], 404);
        }

        $userId = $request->user()->id;
        $isOwner = $comment->user_id === $userId;
        $isTaskCreator = $task->created_by === $userId;

        $project = $task->project;
        $isProjectManager = $project->isManager($userId);

        if (!$isOwner && !$isTaskCreator && !$isProjectManager) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this comment',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $comment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function checkTaskAccess(Request $request, Task $task): void
    {
        $userId = $request->user()?->id;

        if (!$userId) {
            abort(401, 'Unauthenticated');
        }

        $project = $task->project;

        if (!$project->isOwner($userId) && !$project->hasUser($userId)) {
            abort(403, 'You do not have access to this task');
        }
    }
}
