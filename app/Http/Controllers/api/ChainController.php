<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Chain;
use App\Models\Project;
use App\Services\ChainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChainController extends Controller
{
    protected ChainService $chainService;

    public function __construct(ChainService $chainService)
    {
        $this->chainService = $chainService;
    }

    public function index(Request $request)
    {
        try {
            $chains = Chain::with(['creator', 'projects'])->get();

            return response()->json([
                'success' => true,
                'data' => $chains,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch chains: '.$e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load chains. Please try again later.',
            ], 500);
        }
    }

    public function myChains(Request $request)
    {
        $userId = $request->user()->id;

        try {
            $chains = Chain::with([
                'projects' => function ($q) use ($userId) {
                    $q->with(['creator', 'reactions'])
                        ->withCount(['users', 'tasks'])
                        ->with([
                            'users' => function ($uq) use ($userId) {
                                $uq->where('user_id', $userId)
                                    ->select('users.id', 'project_users.role');
                            },
                        ]);
                },
            ])
                ->withCount('projects')
                ->where('created_by', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($chains as $chain) {
                foreach ($chain->projects as $project) {
                    $pivot = $project->users->first();
                    $project->user_role = $pivot?->pivot->role ?? 'none';
                    $project->is_owner = $project->created_by === $userId;
                    $project->setRelation('chains', collect([$chain]));
                }

                $chain->projects = ProjectResource::collection($chain->projects);
            }

            return response()->json([
                'success' => true,
                'data' => $chains,
                'total' => $chains->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch my chains: '.$e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load chains. Please try again later.',
            ], 500);
        }
    }

    public function show(Chain $chain, Request $request)
    {
        try {
            $chain->load(['projects', 'projects.creator', 'projects.users']);

            return response()->json([
                'success' => true,
                'data' => $chain,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch chain: '.$e->getMessage(), [
                'chain_id' => $chain->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load chain. Please try again later.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $chain = Chain::create([
                'name' => $request->name,
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $chain,
                'message' => 'Chain created successfully.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create chain: '.$e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create chain. Please try again later.',
            ], 500);
        }
    }

    public function addProject(Request $request, Chain $chain)
    {
        try {
            if ($chain->created_by !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the chain creator can add projects.',
                ], 403);
            }

            $request->validate([
                'project_id' => 'required|exists:projects,id',
                'position' => 'nullable|integer|min:0',
            ]);

            $project = Project::findOrFail($request->project_id);

            if ($project->created_by !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must own the project to add it to a chain.',
                ], 403);
            }

            $chain = $this->chainService->addToChain($chain->id, $project->id, $request->position);

            return response()->json([
                'success' => true,
                'data' => $chain,
                'message' => 'Project added to chain successfully.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to add project to chain: '.$e->getMessage(), [
                'chain_id' => $chain->id,
                'user_id' => $request->user()->id,
                'project_id' => $request->project_id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add project to chain. Please try again later.',
            ], 500);
        }
    }

    public function removeProject(Request $request, Chain $chain, Project $project)
    {
        try {
            $isChainCreator = $chain->created_by === $request->user()->id;
            $isProjectOwner = $project->created_by === $request->user()->id;

            if (! $isChainCreator && ! $isProjectOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to remove this project from the chain.',
                ], 403);
            }

            $chain = $this->chainService->removeFromChain($chain->id, $project->id);

            return response()->json([
                'success' => true,
                'data' => $chain,
                'message' => 'Project removed from chain successfully.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to remove project from chain: '.$e->getMessage(), [
                'chain_id' => $chain->id,
                'user_id' => $request->user()->id,
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove project from chain. Please try again later.',
            ], 500);
        }
    }

    public function reorder(Request $request, Chain $chain)
    {
        try {
            if ($chain->created_by !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the chain creator can reorder projects.',
                ], 403);
            }

            $request->validate([
                'project_ids' => 'required|array',
                'project_ids.*' => 'integer|exists:projects,id',
            ]);

            $chain = $this->chainService->reorderChain($chain->id, $request->project_ids);

            return response()->json([
                'success' => true,
                'data' => $chain,
                'message' => 'Chain reordered successfully.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to reorder chain: '.$e->getMessage(), [
                'chain_id' => $chain->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder chain. Please try again later.',
            ], 500);
        }
    }

    public function updateChainName(Request $request, Chain $chain)
    {
        try {
            if ($chain->created_by !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the chain creator can update this chain.',
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $chain->update(['name' => $validated['name']]);

            return response()->json([
                'success' => true,
                'data' => $chain,
                'message' => 'Chain name updated successfully.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update chain: '.$e->getMessage(), [
                'chain_id' => $chain->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update chain. Please try again later.',
            ], 500);
        }
    }
}
