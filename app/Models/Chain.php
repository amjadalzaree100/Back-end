<?php

namespace App\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Chain extends Model
{
    protected $fillable = [
        'name',
        'created_by',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'chain_projects')
            ->withPivot('order')
            ->orderBy('chain_projects.order')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function addProject(int $projectId, ?int $position = null): bool
    {
        if ($this->projects()->where('project_id', $projectId)->exists()) {
            return false;
        }

        $maxOrder = $this->projects()->max('order') ?? -1;

        if ($position === null || $position > $maxOrder + 1) {
            $position = $maxOrder + 1;
        }

        if ($position <= $maxOrder) {
            $this->projects()
                ->wherePivot('order', '>=', $position)
                ->each(function ($project) {
                    $project->pivot->increment('order');
                });
        }

        $this->projects()->attach($projectId, ['order' => $position]);
        return true;
    }

    public function removeProject(int $projectId): bool
    {
        $pivot = $this->projects()->where('project_id', $projectId)->first();
        if (!$pivot) {
            return false;
        }

        $deletedOrder = $pivot->pivot->order;
        $this->projects()->detach($projectId);

        $this->projects()
            ->wherePivot('order', '>', $deletedOrder)
            ->each(function ($project) {
                $project->pivot->decrement('order');
            });

        return true;
    }

    public function reorderProjects(array $orderedIds): bool
    {
        foreach ($orderedIds as $index => $id) {
            $this->projects()->updateExistingPivot($id, ['order' => $index]);
        }
        return true;
    }
}