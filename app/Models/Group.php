<?php

namespace App\Models;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


class Group extends Model
{
    use SoftDeletes;

    protected $table = 'groups';

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'avatar',
        'manager_id',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'manager_id' => 'integer',
    ];

    // ============== Relationships ==============

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('added_by', 'joined_at')
            ->withTimestamps();
    }

    public function groupTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'group_id');
    }

    // ============== Helper Methods ==============

    public function isManager(int $userId): bool
    {
        return $this->manager_id === $userId;
    }

    public function isMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function canUserManage(int $userId): bool
    {
        return $this->isManager($userId) || $this->project->isOwner($userId);
    }

    public function addMember(int $userId, int $addedByUserId): bool
    {
        if ($this->isMember($userId)) {
            return false;
        }

        $this->members()->attach($userId, ['added_by' => $addedByUserId]);
        return true;
    }

    public function removeMember(int $userId): bool
    {
        if (!$this->isMember($userId)) {
            return false;
        }

        if ($this->isManager($userId)) {
            return false;
        }

        $this->members()->detach($userId);
        return true;
    }

    public function transferManagerShip(int $newManagerId): bool
    {
        if (!$this->isMember($newManagerId)) {
            return false;
        }

        $oldManagerId = $this->manager_id;

        $this->update(['manager_id' => $newManagerId]);

        if ($oldManagerId !== $this->project->created_by) {
            $this->addMember($oldManagerId, $newManagerId);
        }

        return true;
    }

    // ============== Scopes ==============

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProject(Builder $query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
