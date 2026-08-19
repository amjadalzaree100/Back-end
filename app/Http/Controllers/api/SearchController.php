<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    /**
     * Perform search based on query string.
     * Supports:
     * - @username  : search only users
     * - #project   : search only projects
     * - default    : search both users and projects
     */
    public function search(SearchRequest $request): JsonResponse
    {
        try {
            $query = $request->input('q');
            $originalQuery = $query;
            $searchType = 'both';
            $limit = $request->input('limit', 5); // default 5

            // Handle special limit values: 'all' or 0 means unlimited
            $unlimited = ($limit === 'all' || $limit == 0);
            $numericLimit = $unlimited ? null : (int) $limit;

            if (str_starts_with($query, '@')) {
                $searchType = 'users';
                $query = ltrim($query, '@');
            } elseif (str_starts_with($query, '#')) {
                $searchType = 'projects';
                $query = ltrim($query, '#');
            }

            if (empty(trim($query))) {
                return $this->emptyResponse($originalQuery);
            }

            $users = [];
            $projects = [];

            if ($searchType === 'both' || $searchType === 'users') {
                $users = $this->searchUsers($request, $query, $numericLimit);
            }
            if ($searchType === 'both' || $searchType === 'projects') {
                $projects = $this->searchProjects($request, $query, $numericLimit);
            }

            $responseData = ['query' => $originalQuery];

            if ($searchType === 'both') {
                $responseData['data'] = ['users' => $users, 'projects' => $projects];
                $responseData['total'] = count($users) + count($projects);
            } elseif ($searchType === 'users') {
                $responseData['data'] = ['users' => $users];
                $responseData['total'] = count($users);
            } else {
                $responseData['data'] = ['projects' => $projects];
                $responseData['total'] = count($projects);
            }

            return response()->json(['success' => true, ...$responseData]);
        } catch (\Exception $e) {
            Log::error('Search failed: ' . $e->getMessage(), ['query' => $request->input('q'), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'An error occurred while searching.'], 500);
        }
    }

    private function emptyResponse(string $originalQuery): JsonResponse
    {
        return response()->json(['success' => true, 'query' => $originalQuery, 'data' => [], 'total' => 0]);
    }

    private function searchUsers(Request $request, string $query, ?int $limit): array
    {
        $keywords = explode(' ', $query);
        // "Not activated" = is_active = false AND email_verified_at IS NULL.
        // Users with only one of the two conditions still appear, so we
        // use the positive `activated` scope (which is the inverse of
        // `notActivated`) rather than the negative scope directly.
        $usersQuery = User::with('profile')->activated();

        $this->applyUserFilters($usersQuery, $request);

        $usersQuery->where(function ($q) use ($keywords, $query) {
            foreach ($keywords as $keyword) {
                $q->orWhere('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('username', 'LIKE', "%{$keyword}%");
            }
            $q->orWhere('email', 'LIKE', "%{$query}%");
        });

        $users = $usersQuery->get();
        /** @var User $user */
        foreach ($users as $user) {
            $user->match_score = $this->calculateUserMatchScore($user, $query);
        }

        // Batch-fetch favorites to avoid N+1.
        $userIds = $users->pluck('id')->all();
        $favoriteUserIds = $this->resolveFavoriteUserIds($userIds);

        $items = $users->values()->map(function ($user) use ($favoriteUserIds) {
            $isFavorite = in_array($user->id, $favoriteUserIds, true);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->profile?->avatar,
                'job_title' => $user->profile?->job_title,
                'is_public' => (bool) ($user->profile?->is_public ?? false),
                'is_favorite' => $isFavorite,
                'match_score' => $user->match_score,
            ];
        })->all();

        // Re-sort: is_favorite DESC, match_score DESC. This must happen
        // BEFORE `take($limit)` so a low-match-score favorite is not
        // cut off before it can bubble to the top.
        usort($items, function ($a, $b) {
            $fa = $a['is_favorite'] ? 1 : 0;
            $fb = $b['is_favorite'] ? 1 : 0;
            if ($fa !== $fb) {
                return $fb <=> $fa;
            }

            return $b['match_score'] <=> $a['match_score'];
        });

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    private function searchProjects(Request $request, string $query, ?int $limit): array
    {
        $keywords = explode(' ', $query);
        $projectsQuery = Project::query();

        $this->applyProjectFilters($projectsQuery, $request);

        $projectsQuery->where(function ($q) use ($keywords, $query) {
            foreach ($keywords as $keyword) {
                $q->orWhere('name', 'LIKE', "%{$keyword}%");
            }
            $q->orWhere('description', 'LIKE', "%{$query}%");
        });

        $projects = $projectsQuery->get();
        /** @var Project $project */
        foreach ($projects as $project) {
            $project->match_score = $this->calculateProjectMatchScore($project, $query);
        }

        // Batch-fetch favorites to avoid N+1.
        $projectIds = $projects->pluck('id')->all();
        $favoriteProjectIds = $this->resolveFavoriteProjectIds($projectIds);

        $items = $projects->values()->map(function ($project) use ($favoriteProjectIds) {
            $isFavorite = in_array($project->id, $favoriteProjectIds, true);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'image' => $project->image,
                'status' => $project->status,
                'visibility' => $project->visibility,
                'created_at' => optional($project->created_at)->toIso8601String(),
                'is_favorite' => $isFavorite,
                'match_score' => $project->match_score,
            ];
        })->all();

        // Re-sort: is_favorite DESC, match_score DESC. This must happen
        // BEFORE `take($limit)` so a low-match-score favorite is not
        // cut off before it can bubble to the top.
        usort($items, function ($a, $b) {
            $fa = $a['is_favorite'] ? 1 : 0;
            $fb = $b['is_favorite'] ? 1 : 0;
            if ($fa !== $fb) {
                return $fb <=> $fa;
            }

            return $b['match_score'] <=> $a['match_score'];
        });

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    /**
     * Apply the 6 user filters to a user query. All filters are optional
     * and AND-combined. Missing/empty values are no-ops.
     */
    private function applyUserFilters(Builder $q, Request $request): Builder
    {
        if ($jobTitle = $request->input('job_title')) {
            $q->whereHas('profile', fn($p) => $p->where('job_title', 'LIKE', "%{$jobTitle}%"));
        }

        if ($location = $request->input('location')) {
            $q->whereHas('profile', fn($p) => $p->where('location', 'LIKE', "%{$location}%"));
        }

        $q->createdBetween($request->input('created_from'), $request->input('created_to'));

        if ($request->filled('is_public_profile')) {
            $q->whereHas('profile', fn($p) => $p->where('is_public', $request->boolean('is_public_profile')));
        }

        if ($skills = $request->input('skills')) {
            $skillList = collect(explode(',', $skills))
                ->map(fn($s) => Str::lower(trim($s)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($skillList)) {
                // Each stored skill has a {name, rating} shape. We compare
                // case-insensitively by lowercasing both sides. The skills
                // column is `json` in Postgres, so we cast to `jsonb` to
                // use `jsonb_array_elements` for per-element matching.
                $q->whereHas('profile', function ($p) use ($skillList) {
                    $p->where(function ($w) use ($skillList) {
                        foreach ($skillList as $skill) {
                            $w->orWhereRaw(
                                "EXISTS (SELECT 1 FROM jsonb_array_elements(skills::jsonb) AS s WHERE LOWER(s->>'name') = ?)",
                                [$skill]
                            );
                        }
                    });
                });
            }
        }

        return $q;
    }

    /**
     * Apply the 6 project filters to a project query. All filters are
     * optional and AND-combined. Missing/empty values are no-ops.
     */
    private function applyProjectFilters(Builder $q, Request $request): Builder
    {
        if ($visibility = $request->input('visibility')) {
            $q->where('visibility', $visibility);
        }

        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }

        if ($request->filled('start_date_from')) {
            $q->where('start_date', '>=', $request->input('start_date_from') . ' 00:00:00');
        }
        if ($request->filled('start_date_to')) {
            $q->where('start_date', '<=', $request->input('start_date_to') . ' 23:59:59');
        }
        if ($request->filled('end_date_from')) {
            $q->where('end_date', '>=', $request->input('end_date_from') . ' 00:00:00');
        }
        if ($request->filled('end_date_to')) {
            $q->where('end_date', '<=', $request->input('end_date_to') . ' 23:59:59');
        }

        $q->createdBetween($request->input('created_from'), $request->input('created_to'));

        if ($createdBy = $request->input('created_by')) {
            $q->where('created_by', (int) $createdBy);
        }

        return $q;
    }

    /**
     * Return the subset of $userIds that the current auth user has
     * favorited. Returns an empty array if there is no auth user or no
     * candidate IDs. Uses a single `whereIn` to avoid N+1.
     *
     * @param  array<int>  $userIds
     * @return array<int>
     */
    private function resolveFavoriteUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return $user->favoriteUsers()
            ->whereIn('favorite_user_id', $userIds)
            ->pluck('favorite_user_id')
            ->map(fn($v) => (int) $v)
            ->all();
    }

    /**
     * Return the subset of $projectIds that the current auth user has
     * favorited. Single `whereIn`, no N+1.
     *
     * @param  array<int>  $projectIds
     * @return array<int>
     */
    private function resolveFavoriteProjectIds(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return $user->favoriteProjects()
            ->whereIn('project_id', $projectIds)
            ->pluck('project_id')
            ->map(fn($v) => (int) $v)
            ->all();
    }

    /**
     * Calculate relevance score for user search results.
     */
    private function calculateUserMatchScore(User $user, string $query): int
    {
        $score = 0;
        $lowerQuery = strtolower($query);
        $lowerName = strtolower($user->name);
        $lowerUsername = strtolower($user->username);

        if ($lowerName === $lowerQuery || $lowerUsername === $lowerQuery) {
            $score += 100;
        } elseif (str_starts_with($lowerName, $lowerQuery) || str_starts_with($lowerUsername, $lowerQuery)) {
            $score += 80;
        } elseif (str_contains($lowerName, " {$lowerQuery} ") || str_contains($lowerUsername, " {$lowerQuery} ")) {
            $score += 60;
        } elseif (str_contains($lowerName, $lowerQuery) || str_contains($lowerUsername, $lowerQuery)) {
            $score += 40;
        }

        if ($user->profile && str_contains(strtolower($user->profile->job_title ?? ''), $lowerQuery)) {
            $score += 20;
        }

        return $score;
    }

    /**
     * Calculate relevance score for project search results.
     */
    private function calculateProjectMatchScore(Project $project, string $query): int
    {
        $score = 0;
        $lowerQuery = strtolower($query);
        $lowerName = strtolower($project->name);

        if ($lowerName === $lowerQuery) {
            $score += 100;
        } elseif (str_starts_with($lowerName, $lowerQuery)) {
            $score += 80;
        } elseif (str_contains($lowerName, " {$lowerQuery} ")) {
            $score += 60;
        } elseif (str_contains($lowerName, $lowerQuery)) {
            $score += 40;
        }

        if ($project->description && str_contains(strtolower($project->description), $lowerQuery)) {
            $score += 15;
        }

        if ($project->status === 'active') {
            $score += 10;
        }

        return $score;
    }
}
