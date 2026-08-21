<?php

namespace App\Models;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'from_project_id',
        'to_project_id',
        'from_task_id',
        'to_task_id',
        'transferred_by',
        'note',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function fromProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'from_project_id');
    }

    public function toProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'to_project_id');
    }

    public function fromTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'from_task_id');
    }

    public function toTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'to_task_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by')->withTrashed();
    }
}