<?php

namespace App\Http\Controllers\api;

use App\Events\ProjectCreated;
use App\Events\ProjectNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Chain;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    public function myProjects(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $search = $request->input('search');
        $role = $request->input('role');           // 'owner', 'manager', 'user', 'observer'
        $status = $request->input('status');       // 'active', 'paused', 'completed'
        $visibility = $request->input('visibility'); // 'private', 'public'

        //  1. Validate sort parameters
        $allowedSorts = ['created_at', 'name', 'updated_at', 'status'];
        $sortBy = $request->input('sort_by', 'created_at');
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $sortDirection = $request->input('sort_direction', 'desc');
        $sortDirection = $sortDirection === 'asc' ? 'asc' : 'desc';

        //  2. Build base query
        $query = Project::query()
            ->with(['reactions', 'creator']) // only needed relations
            ->withCount(['users', 'tasks'])
            ->where(function ($q) use ($userId) {
                // Projects where user is owner or member
                $q->where('created_by', $userId)
                    ->orWhereHas('users', fn($sub) => $sub->where('user_id', $userId));
            });

        //  3. Apply filters
        // Filter by role
        if ($role) {
            if ($role === 'owner') {
                $query->where('created_by', $userId);
            } else {
                // manager, user, observer
                $query->whereHas('users', function ($q) use ($userId, $role) {
                    $q->where('user_id', $userId)->where('role', $role);
                });
            }
        }

        // Filter by project status
        if ($status && in_array($status, ['active', 'paused', 'completed'])) {
            $query->where('status', $status);
        }

        // Filter by visibility
        if ($visibility && in_array($visibility, ['private', 'public'])) {
            $query->where('visibility', $visibility);
        }

        // Search filter (name or description)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        //  4. Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        //  5. Execute query with eager loading of current user's role
        // Load only the current user's pivot data for each project (one extra query)
        $projects = $query->with([
            'users' => function ($q) use ($userId) {
                $q->where('user_id', $userId)->select('users.id', 'project_users.role');
            }
        ])->get();

        //  6. Add computed properties
        foreach ($projects as $project) {
            $pivot = $project->users->first();
            $project->user_role = $pivot?->pivot->role ?? 'none';
            $project->is_owner = $project->created_by === $userId;
        }

        //  7. Return response
        return response()->json([
            'success' => true,
            'data' => ProjectResource::collection($projects),
            'total' => $projects->count(),
        ]);
    }
    public function show(Request $request, Project $project): JsonResponse
    {
        $userId = $request->user()->id;

        $isOwner = $project->created_by === $userId;
        $isMember = $project->hasUser($userId);
        $isPublic = $project->visibility === 'public';

        $canViewFullDetails = $isOwner || $isMember || $isPublic;

        $project->load([
            'projectComments' => function ($q) {
                $q->with(['user', 'user.profile'])->whereNull('parent_id')->latest();
            },
            'reactions',
            'chains',
        ]);

        if ($canViewFullDetails) {
            $project->load([
                'creator',
                'taskStatuses' => fn($q) => $q->orderBy('position'),
            ]);
        }

        $project->loadCount(['users', 'tasks']);

        if ($isOwner || $isMember) {
            $role = $project->users()
                ->where('user_id', $userId)
                ->value('role') ?? 'none';
            $project->user_role = $role;
            $project->is_owner = $isOwner;
        } else {
            $project->user_role = null;
            $project->is_owner = false;
        }

        return response()->json([
            'success' => true,
            'data' => (new ProjectResource($project))->setFullDetails($canViewFullDetails)
        ]);
    }
    public function store(StoreProjectRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = [
                'name' => $request->name,
                'description' => $request->description,
                'image' => $request->image,
                'status' => 'active',
                'visibility' => $request->visibility ?? 'private',
                'start_date' => now(),
                'end_date' => null,
                'created_by' => $request->user()->id,
                'allow_join_requests' => $request->input('allow_join_requests', true),
                'allow_commit' => $request->input('allow_commit', true),
                'allow_reactions' => $request->input('allow_reactions', true),
            ];

            // Private projects cannot accept join requests.
            if (($data['visibility'] ?? null) === 'private') {
                $data['allow_join_requests'] = false;
            }

            $project = Project::create($data);

            $project->users()->attach($request->user()->id, ['role' => 'owner']);

            // Handle chain assignment
            if ($request->has('chain_id')) {
                $chain = Chain::findOrFail($request->chain_id);
                // Ensure user is the creator of the chain or owner of the project
                if ($chain->created_by !== $request->user()->id) {
                    throw new \Exception('You do not own this chain.');
                }
                $chain->addProject($project->id);
                $project->update(['chain_id' => $chain->id]);
            } else {
                $chain = Chain::create([
                    'name' => $project->name . ' Chain',
                    'created_by' => $request->user()->id,
                ]);
                $chain->addProject($project->id);
                $project->update(['chain_id' => $chain->id]);
            }

            event(new ProjectCreated($project));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => new ProjectResource($project->load('creator'))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Project creation failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the project. Please try again later.'
            ], 500);
        }
    }
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $userId = $request->user()->id;

        // 1. Authorization check
        $isOwner = $project->created_by === $userId;
        $isManager = $project->isManager($userId);
        if (!$isOwner && !$isManager) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this project'
            ], 403);
        }

        // 2. Prepare data (only allowed fields)
        $data = $request->only([
            'name',
            'description',
            'image',
            'visibility',
            'allow_join_requests',
            'allow_commit',
            'allow_reactions',
        ]);

        // If visibility is being changed to private, private projects cannot
        // accept join requests.
        if (($data['visibility'] ?? null) === 'private') {
            $data['allow_join_requests'] = false;
        }

        // 3. Handle status change separately
        $oldStatus = $project->status;
        $newStatus = $request->input('status', $oldStatus);

        // Validate status transition (optional but recommended)
        if ($request->has('status') && !in_array($newStatus, ['active', 'paused', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status value'
            ], 422);
        }

        // Prevent changing status if project is completed and not owner? (optional)

        // 4. Auto-set end_date when completing project
        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            $data['end_date'] = now();
            $data['status'] = 'completed';
        }
        // Clear end_date when moving out of completed
        elseif ($oldStatus === 'completed' && $newStatus !== 'completed') {
            $data['end_date'] = null;
            $data['status'] = $newStatus;
        }
        // Normal status change without completion logic
        elseif ($request->has('status')) {
            $data['status'] = $newStatus;
        }

        // 5. Remove start_date and end_date from data (never allow manual update)
        unset($data['start_date'], $data['end_date']);

        try {
            DB::beginTransaction();

            $project->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => new ProjectResource($project->load('creator'))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Project update failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the project. Please try again later.'
            ], 500);
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $projects = Project::onlyTrashed()
                ->where('created_by', $userId)
                ->with(['creator'])
                ->orderBy('deleted_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ProjectResource::collection($projects),
                'total' => $projects->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Fetching trashed projects failed: ' . $e->getMessage(), [
                'user_id' => $userId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load trashed projects. Please try again later.'
            ], 500);
        }
    }
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $userId = $request->user()->id;

        // Only project owner can delete
        if ($project->created_by !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Only project owner can delete the project'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $project->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Project deletion failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the project. Please try again later.'
            ], 500);
        }
    }
    public function restore(Request $request, int $projectId): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $project = Project::onlyTrashed()->find($projectId);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found in trash'
                ], 404);
            }

            if ($project->created_by !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to restore this project'
                ], 403);
            }

            DB::beginTransaction();

            $project->restore();

            if ($project->chain_id) {
                $chain = Chain::find($project->chain_id);
                if ($chain && !$chain->projects()->where('project_id', $project->id)->exists()) {
                    $chain->addProject($project->id);
                } elseif (!$chain) {
                    $newChain = Chain::create([
                        'name' => $project->name . ' Chain',
                        'created_by' => $userId,
                    ]);
                    $newChain->addProject($project->id);
                    $project->update(['chain_id' => $newChain->id]);
                }
            } else {
                $newChain = Chain::create([
                    'name' => $project->name . ' Chain',
                    'created_by' => $userId,
                ]);
                $newChain->addProject($project->id);
                $project->update(['chain_id' => $newChain->id]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project restored successfully.',
                'data' => new ProjectResource($project->load('creator'))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Project restore failed: ' . $e->getMessage(), [
                'project_id' => $projectId,
                'user_id' => $userId ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore project. Please try again later.'
            ], 500);
        }
    }
    public function forceDelete(Request $request, int $projectId): JsonResponse
    {
        $userId = $request->user()->id;
        $project = Project::onlyTrashed()->find($projectId);

        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found in trash'], 404);
        }

        if ($project->created_by !== $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            // 1. Delete reactions (if not already handled by cascade)
            $project->reactions()->forceDelete();

            // 2. Delete tasks and related data
            foreach ($project->tasks()->withTrashed()->get() as $task) {
                $task->comments()->forceDelete();
                $task->forceDelete();
            }

            // 3. Delete groups and their tasks
            foreach ($project->groups()->withTrashed()->get() as $group) {
                foreach ($group->groupTasks()->withTrashed()->get() as $groupTask) {
                    $groupTask->forceDelete();
                }
                $group->members()->detach();
                $group->forceDelete();
            }

            // 4. Detach users and favorites
            $project->users()->detach();
            $project->favoritedBy()->detach();

            // 5. Delete requests (join & invitations)
            $project->joinRequests()->forceDelete();
            $project->invitations()->forceDelete();

            // 6. Delete comments and reports
            $project->projectComments()->forceDelete();
            $project->projectReports()->forceDelete();

            // 7. Capture the chain this project was attached to (before deletion)
            $chain = $project->activeChain();

            // 8. Finally, force delete the project itself
            $project->forceDelete();

            // 9. Clean up orphaned chains (no projects left, including trashed)
            if ($chain && $chain->projects()->withTrashed()->count() === 0) {
                $chain->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project and all related data permanently deleted'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Force delete project failed: ' . $e->getMessage(), [
                'project_id' => $projectId,
                'user_id' => $userId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete project. Please try again later.'
            ], 500);
        }
    }
    public function emptyTrash(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Get all trashed projects owned by the user
        $projects = Project::onlyTrashed()->where('created_by', $userId)->get();

        if ($projects->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No projects in trash',
                'deleted_count' => 0
            ]);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($projects as $project) {
            // Call forceDelete for each project, reusing the existing method
            $response = $this->forceDelete($request, $project->id);

            // Check if deletion was successful
            $responseData = $response->getData();
            if ($responseData->success === true) {
                $deletedCount++;
            } else {
                $errors[] = [
                    'project_id' => $project->id,
                    'message' => $responseData->message ?? 'Unknown error'
                ];
            }
        }

        if ($deletedCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete any projects',
                'errors' => $errors
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} project(s) permanently deleted",
            'deleted_count' => $deletedCount,
            'errors' => $errors
        ]);
    }

    //   Update project status (active/paused/completed).
    public function updateStatus(Request $request, Project $project): JsonResponse
    {
        $userId = $request->user()->id;

        // 1. Authorization
        if (!$project->isOwner($userId) && !$project->isManager($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to change project status'
            ], 403);
        }

        // 2. Validate request
        $request->validate([
            'status' => 'required|in:active,paused,completed'
        ]);

        $oldStatus = $project->status;
        $newStatus = $request->status;

        // 3. Prepare data
        $data = ['status' => $newStatus];

        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            $data['end_date'] = now();
        } elseif ($oldStatus === 'completed' && $newStatus !== 'completed') {
            $data['end_date'] = null;
        }

        try {
            DB::beginTransaction();
            $project->update($data);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Project status update failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project status. Please try again later.'
            ], 500);
        }

        $memberIds = $project->users()->pluck('users.id')->toArray();

        ProjectNotificationEvent::dispatch(
            userIds: $memberIds,
            scenario: 'status_changed',
            project: $project,
            actor: $request->user(),
            extra: ['status' => $newStatus],
        );

        return response()->json([
            'success' => true,
            'message' => "Project status updated to {$newStatus}",
            'data' => [
                'status' => $project->status,
                'status_label' => $project->status_label,
                'end_date' => $project->end_date?->toISOString()
            ]
        ]);
    }

    //  Update project visibility (private/public).
    public function updateVisibility(Request $request, Project $project): JsonResponse
    {
        $userId = $request->user()->id;

        // 1. Authorization
        if (!$project->isOwner($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to change project visibility'
            ], 403);
        }

        // 2. Validate request
        $request->validate([
            'visibility' => 'required|in:private,public'
        ]);

        try {
            DB::beginTransaction();
            $project->update([
                'visibility' => $request->visibility,
                // Private projects cannot accept join requests.
                'allow_join_requests' => $request->visibility === 'private' ? false : $project->allow_join_requests,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Project visibility updated to {$request->visibility}",
                'data' => [
                    'visibility' => $project->visibility,
                    'visibility_label' => $project->visibility_label
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Project visibility update failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $userId,
                'new_visibility' => $request->visibility
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project visibility. Please try again later.'
            ], 500);
        }
    }




    /**
     * Get all active projects owned by the authenticated user.
     * (Projects that are not paused and not completed).
     */
    public function myActiveOwnedProjects(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $projects = Project::where('created_by', $userId)
                ->where('status', 'active')
                ->with(['creator', 'reactions'])
                ->withCount(['users', 'tasks'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ProjectResource::collection($projects),
                'total' => $projects->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching active owned projects failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load projects. Please try again later.'
            ], 500);
        }
    }
}
