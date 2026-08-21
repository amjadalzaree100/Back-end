<?php

namespace App\Models;

use App\Models\Project;
use App\Models\ProjectReaction;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_active',
        'suspended_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'deleted',
    ];

    public function getDeletedAttribute(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    protected static function booted()
    {
        static::created(function ($user) {
            $user->profile()->create([
                'user_id' => $user->id,
                'language' => 'en',
                'theme' => 'dark',
                'is_public' => true,
                'allow_messages' => true,
                'allow_invitation_requests' => true,
                'projects_count' => 0,
                'tasks_completed' => 0,
                'report_count' => 0,
            ]);
            $user->note()->create([
                'title' => 'My Note',
                'content' => null,
                'color' => '#1b1919',
            ]);
        });
    }

    public function managedGroups()
    {
        return $this->hasMany(Group::class, 'manager_id');
    }

    

    public function ownedProjects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function projectReactions()
    {
        return $this->hasMany(ProjectReaction::class);
    }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): void
    {
        $this->update(['email_verified_at' => now()]);
    }

    /**
     * A user is "activated" when they are both active AND have a verified
     * email. Used by the search endpoint as the "not activated" exclusion
     * rule — users with `is_active = false` AND `email_verified_at IS NULL`
     * are the only users excluded from search results.
     */
    public function isActivated(): bool
    {
        return $this->is_active && $this->hasVerifiedEmail();
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function suspend(): void
    {
        $this->update([
            'is_active' => false,
            'suspended_at' => now(),
        ]);
    }

    public function reactivate(): void
    {
        $this->update([
            'is_active' => true,
            'suspended_at' => null,
        ]);
    }

    /**
     * Scope to users that are NOT activated: `is_active = false` AND
     * `email_verified_at IS NULL`. Both conditions must hold.
     */
    public function scopeNotActivated(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_active', false)
                ->whereNull('email_verified_at');
        });
    }

    /**
     * Scope to users that ARE activated: NOT (`is_active = false` AND
     * `email_verified_at IS NULL`). In other words, exclude users that
     * satisfy both "not activated" conditions. This is the positive
     * filter used by the search endpoint.
     */
    public function scopeActivated(Builder $query): Builder
    {
        return $query->whereNot(function ($q) {
            $q->where('is_active', false)
                ->whereNull('email_verified_at');
        });
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function blockedUsers()
    {
        return $this->hasMany(BlockedUser::class, 'user_id');
    }

    public function blockUser(User $user, ?string $reason = null): bool
    {
        if ($this->id === $user->id) {
            return false;
        }

        return (bool) BlockedUser::updateOrCreate([
            'user_id' => $this->id,
            'blocked_user_id' => $user->id,
        ], [
            'reason' => $reason,
        ]);
    }

    public function unblockUser(User $user): bool
    {
        return (bool) BlockedUser::where('user_id', $this->id)
            ->where('blocked_user_id', $user->id)
            ->delete();
    }

    public function isBlocking(User $user): bool
    {
        return BlockedUser::where('user_id', $this->id)
            ->where('blocked_user_id', $user->id)
            ->exists();
    }

    public function isBlockedBy(User $user): bool
    {
        return BlockedUser::where('user_id', $user->id)
            ->where('blocked_user_id', $this->id)
            ->exists();
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function sentRequests()
    {
        return $this->hasMany(Request::class, 'sender_id');
    }

    public function receivedRequests()
    {
        return $this->hasMany(Request::class, 'receiver_id');
    }

    public function reportsMade()
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function note()
    {
        return $this->hasOne(Note::class);
    }

    public function projectReports()
    {
        return $this->hasMany(ProjectReport::class, 'reporter_id');
    }

    public function projectComments()
    {
        return $this->hasMany(ProjectComment::class, 'user_id');
    }

    public function hasReported(User $user): bool
    {
        return Report::where('reporter_id', $this->id)
            ->where('reported_user_id', $user->id)
            ->exists();
    }

    public function getReportsCountAttribute(): int
    {
        return Report::where('reported_user_id', $this->id)->count();
    }

    public function ratingsGiven()
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    public function ratingsReceived()
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    public function getRatingsCountAttribute(): int
    {
        return $this->ratings_count ?? $this->ratingsReceived()->count();
    }

    public function getRatingsAverageAttribute(): float
    {
        return (float) round(
            (float) $this->ratingsReceived()->avg('rating'),
            2
        );
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'user_id');
    }

    public function favoriteUsers()
    {
        return $this->belongsToMany(User::class, 'favorites', 'user_id', 'favorite_user_id')
            ->withTimestamps();
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites', 'favorite_user_id', 'user_id')
            ->withTimestamps();
    }

    public function addToFavorites(User $user): bool
    {
        if ($this->id === $user->id) {
            return false;
        }

        if ($this->favoriteUsers()->where('favorite_user_id', $user->id)->exists()) {
            return false;
        }

        $this->favoriteUsers()->attach($user->id);

        return true;
    }

    public function removeFromFavorites(User $user): bool
    {
        return $this->favoriteUsers()->detach($user->id) > 0;
    }

    public function isFavorite(User $user): bool
    {
        return $this->favoriteUsers()->where('favorite_user_id', $user->id)->exists();
    }

    public function favoriteProjects()
    {
        return $this->belongsToMany(Project::class, 'favorite_projects', 'user_id', 'project_id')
            ->withTimestamps();
    }

    public function addProjectToFavorites(Project $project): bool
    {
        if ($this->favoriteProjects()->where('project_id', $project->id)->exists()) {
            return false;
        }

        $this->favoriteProjects()->attach($project->id);

        return true;
    }

    public function removeProjectFromFavorites(Project $project): bool
    {
        return $this->favoriteProjects()->detach($project->id) > 0;
    }

    public function isProjectFavorite(Project $project): bool
    {
        return $this->favoriteProjects()->where('project_id', $project->id)->exists();
    }

    /**
     * Scope to users created within an optional date range (half-open: includes
     * all timestamps on `date_to`). Empty / null bounds are ignored.
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
