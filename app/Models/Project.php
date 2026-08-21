<?php

namespace App\Models;

use App\Models\Chain;
use App\Models\ProjectComment;
use App\Models\ProjectReaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'projects';

    protected $fillable = [
        'name',
        'description',
        'image',
        'status',
        'visibility',
        'start_date',
        'end_date',
        'created_by',
        'allow_join_requests',
        'allow_commit',
        'allow_reactions',

    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'allow_join_requests' => 'boolean',
        'allow_commit' => 'boolean',
        'allow_reactions' => 'boolean',
    ];

    protected $appends = [
        'users_count',
        'completed_tasks_count',
    ];

    // ============== Relationships ==============

    public function getChainIdAttribute(): ?int
    {
        $chain = $this->chains()->first();
        return $chain?->id;
    }

    public function chains(): BelongsToMany
    {
        return $this->belongsToMany(Chain::class, 'chain_projects')
            ->withPivot('order')
            ->withTimestamps();
    }

    public function activeChain(): ?Chain
    {
        return $this->chains()->first();
    }

    public function joinRequests()
    {
        return $this->hasMany(Request::class)->where('type', 'join_request');
    }

    public function invitations()
    {
        return $this->hasMany(Request::class)->where('type', 'invitation');
    }

    public function sentJoinRequests()
    {
        return $this->hasMany(Request::class, 'project_id')->where('type', 'join_request');
    }

    public function projectComments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorite_projects', 'project_id', 'user_id')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function taskStatuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class);
    }

    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Comment::class,
            Task::class,
            'project_id',
            'task_id',
            'id',
            'id'
        );
    }

    public function projectReports(): HasMany
    {
        return $this->hasMany(ProjectReport::class, 'reported_project_id');
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    // ============== Helper Methods ==============

    public function isOwner(int $userId): bool
    {
        return $this->created_by === $userId;
    }

    public function hasUser(int $userId): bool
    {
        return $this->users()->where('user_id', $userId)->exists();
    }

    public function getUserRole(int $userId): ?string
    {
        $user = $this->users()->where('user_id', $userId)->first();

        return $user?->pivot->role;
    }

    public function isManager(int $userId): bool
    {
        $role = $this->getUserRole($userId);

        return $role === 'owner' || $role === 'manager';
    }

    public function getUsersCountAttribute(): int
    {
        return $this->users_count ?? $this->users()->count();
    }

    public function getCompletedTasksCountAttribute(): int
    {
        return $this->tasks()->whereNotNull('completed_at')->count();
    }

    // ============== Management Methods ==============

    public function addUser(int $userId, string $role = 'user'): bool
    {
        if ($this->hasUser($userId)) {
            return false;
        }

        $this->users()->attach($userId, ['role' => $role]);

        return true;
    }

    public function removeUser(int $userId): bool
    {
        if (!$this->hasUser($userId)) {
            return false;
        }

        $this->users()->detach($userId);

        return true;
    }

    public function updateUserRole(int $userId, string $role): bool
    {
        if (!$this->hasUser($userId)) {
            return false;
        }

        $this->users()->updateExistingPivot($userId, ['role' => $role]);

        return true;
    }

    public function getManagers(): Collection
    {
        return $this->users()
            ->wherePivotIn('role', ['owner', 'manager'])
            ->get();
    }

    public function reactions()
    {
        return $this->hasMany(ProjectReaction::class);
    }

    public function reactedBy(User $user): bool
    {
        return $this->reactions()->where('user_id', $user->id)->exists();
    }

    public function getUserReactionAttribute(): ?string
    {
        $user = request()->user();
        if (!$user) {
            return null;
        }

        return $this->reactions->where('user_id', $user->id)->first()?->reaction_type;
    }

    public function getReactionCountsAttribute(): array
    {
        $counts = $this->reactions()
            ->select('reaction_type', \DB::raw('count(*) as total'))
            ->groupBy('reaction_type')
            ->pluck('total', 'reaction_type')
            ->toArray();

        return [
            'like' => $counts['like'] ?? 0,
            'love' => $counts['love'] ?? 0,
            'dislike' => $counts['dislike'] ?? 0,
        ];
    }

    // ============== Query Scopes ==============

    public function scopeActive(Builder $query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForUser(Builder $query, int $userId)
    {
        return $query->where('created_by', $userId)
            ->orWhereHas('users', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
    }

    /**
     * Scope to projects created within an optional date range (half-open: includes
     * all timestamps on `date_to`). Empty / null bounds are ignored. Note: when
     * combined with `onlyTrashed()`, this still filters on `created_at`, not
     * `deleted_at` — see plan §3.2.
     */
    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query;
    }
}
