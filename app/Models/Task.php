<?php

namespace App\Models;

use App\Events\TaskCompleted;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\TaskAssignmentHistory;
use App\Models\TaskStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;



class Task extends Model
{
    use SoftDeletes;

    protected $table = 'tasks';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status_id',
        'priority',
        'due_date',
        'position',
        'created_by',
        'assigned_to',
        'completed_at',
        'started_at',
        'parent_task_id',
        'allow_subtasks',
        'can_be_assigned',
        'assigned_group_id',
        'is_archived',
        'transferred_from_task_id',
        'transferred_to_task_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'allow_subtasks' => 'boolean',
        'can_be_assigned' => 'boolean',
    ];

    protected $attributes = [
        'priority' => 'medium',
        'position' => 0,
    ];

    protected static function booted()
    {
        static::deleting(function ($task) {
            if ($task->isForceDeleting()) {
                $task->comments()->forceDelete();
                $task->subTasks()->forceDelete();
                $task->dependencies()->detach();
            } else {
                $task->comments()->delete();
                $task->subTasks()->delete();
            }
        });

        static::restoring(function ($task) {
            $task->comments()->withTrashed()->restore();
            $task->subTasks()->withTrashed()->restore();
        });

        static::updating(function ($task) {
            if ($task->is_archived && !$task->isForceDeleting()) {
                throw new \Exception('Cannot update an archived task.');
            }
        });

        static::deleting(function ($task) {
            if ($task->is_archived) {
                throw new \Exception('Cannot delete an archived task.');
            }
        });

    }

    public function statusHistories()
    {
        return $this->hasMany(TaskStatusHistory::class)->orderBy('changed_at', 'desc');
    }

    public function assignmentHistories()
    {
        return $this->hasMany(TaskAssignmentHistory::class)->orderBy('assigned_at', 'desc');
    }
    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'transferred_from_task_id');
    }

    public function transferredTo(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'transferred_to_task_id');
    }

    // Check if the task can be transferred (all subtasks must be completed)
    public function canBeTransferred(): bool
    {
        if ($this->subTasks()->whereNull('completed_at')->exists()) {
            return false;
        }
        return true;
    }

    // Archive the task (make it read-only)
    public function archive(): bool
    {
        return $this->update(['is_archived' => true]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class,
            'task_dependencies',
            'task_id',
            'depends_on_task_id'
        )->withPivot('type')
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class,
            'task_dependencies',
            'depends_on_task_id',
            'task_id'
        )->withPivot('type')
            ->withTimestamps();
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function assignedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'assigned_group_id');
    }

    public function isProjectTask(): bool
    {
        return is_null($this->assigned_group_id) && is_null($this->parent_task_id);
    }

    public function isGroupTask(): bool
    {
        return !is_null($this->assigned_group_id);
    }

    public function isSubTask(): bool
    {
        return !is_null($this->parent_task_id);
    }

    public function reminders(): BelongsToMany
    {
        return $this->belongsToMany(Reminder::class, 'reminder_task');
    }



    public function isStarted(): bool
    {
        return !is_null($this->started_at);
    }

    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    public function isOverdue(): bool
    {
        if ($this->isCompleted() || is_null($this->due_date)) {
            return false;
        }
        return $this->due_date->isPast();
    }

    public function start(): void
    {
        if (!$this->isStarted()) {
            $this->update(['started_at' => now()]);
        }
    }

    public function complete(): void
    {
        if (!$this->isCompleted() && $this->canBeCompleted()) {
            $this->update(['completed_at' => now()]);
            event(new TaskCompleted($this));
        }
    }

    public function isBlocked(): bool
    {
        foreach ($this->dependencies as $dependency) {
            $type = $dependency->pivot->type;

            if ($type === 'FS' && !$dependency->isCompleted()) {
                return true;
            }

            if ($type === 'SS' && !$dependency->isStarted()) {
                return true;
            }

            if ($type === 'FF' && !$dependency->isCompleted()) {
                return true;
            }

            if ($type === 'SF' && !$dependency->isStarted()) {
                return true;
            }
        }
        return false;
    }

    public function canBeStarted(): bool
    {
        foreach ($this->dependencies as $dependency) {
            $type = $dependency->pivot->type;

            if ($type === 'FS' && !$dependency->isCompleted()) {
                return false;
            }

            if ($type === 'SS' && !$dependency->isStarted()) {
                return false;
            }
        }
        return true;
    }

    public function canBeCompleted(): bool
    {
        foreach ($this->dependencies as $dependency) {
            $type = $dependency->pivot->type;

            if ($type === 'FS' && !$dependency->isCompleted()) {
                return false;
            }

            // if ($type === 'SS' && !$dependency->isStarted()) {
            //     return false;
            // }

            if ($type === 'FF' && !$dependency->isCompleted()) {
                return false;
            }

            if ($type === 'SF' && !$dependency->isStarted()) {
                return false;
            }
        }
        return true;
    }

    public function getTypeLabel(string $type): string
    {
        return match ($type) {
            'FS' => 'Finish to Start',
            'SS' => 'Start to Start',
            'FF' => 'Finish to Finish',
            'SF' => 'Start to Finish',
            default => 'Unknown',
        };
    }

    public function getTypeDescription(string $type): string
    {
        return match ($type) {
            'FS' => 'This task cannot be started until the dependency is completed',
            'SS' => 'This task cannot be started until the dependency is started',
            'FF' => 'This task cannot be completed until the dependency is completed',
            'SF' => 'This task cannot be completed until the dependency is started',
            default => '',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'Urgent',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => 'Medium',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => '#EF4444',
            'high' => '#F97316',
            'medium' => '#F59E0B',
            'low' => '#10B981',
            default => '#6B7280',
        };
    }

    public function getDueDateFormattedAttribute(): ?string
    {
        return $this->due_date?->format('Y-m-d');
    }

    public function getStartedAtFormattedAttribute(): ?string
    {
        return $this->started_at?->format('Y-m-d H:i:s');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->isOverdue();
    }

    public function getIsStartedAttribute(): bool
    {
        return $this->isStarted();
    }

    public function getIsBlockedAttribute(): bool
    {
        return $this->isBlocked();
    }

    public function getCanBeStartedAttribute(): bool
    {
        return $this->canBeStarted();
    }

    public function getCanBeCompletedAttribute(): bool
    {
        return $this->canBeCompleted();
    }

    public function getAssignmentsCountAttribute(): int
    {
        return $this->assigned_to ? 1 : 0;
    }

    public function getDependenciesCountAttribute(): int
    {
        return $this->dependencies()->count();
    }

    public function getDependentsCountAttribute(): int
    {
        return $this->dependents()->count();
    }

    public function scopeByProject(Builder $query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByStatus(Builder $query, int $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    public function scopeByAssignee(Builder $query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeOverdue(Builder $query)
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeByPriority(Builder $query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeIncomplete(Builder $query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeNotStarted(Builder $query)
    {
        return $query->whereNull('started_at');
    }

    public function scopeInProgress(Builder $query)
    {
        return $query->whereNotNull('started_at')->whereNull('completed_at');
    }

    public function scopeDueToday(Builder $query)
    {
        return $query->whereDate('due_date', today());
    }

    public function scopeDueThisWeek(Builder $query)
    {
        return $query->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeProjectTasks(Builder $query, ?int $userId = null)
    {
        $query->whereNull('parent_task_id')
            ->whereNull('assigned_group_id');

        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)
                    ->orWhere('created_by', $userId);
            });
        }

        return $query;
    }

    public function scopeGroupTasks(Builder $query)
    {
        return $query->whereNotNull('assigned_group_id');
    }

    public function scopeSubTasks(Builder $query)
    {
        return $query->whereNotNull('parent_task_id');
    }

    public function canBeAssigned(): bool
    {
        if (!is_null($this->parent_task_id)) {
            return true;
        }

        return $this->can_be_assigned;
    }


}
