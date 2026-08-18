<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comments';

    protected $fillable = [
        'task_id',
        'user_id',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ============== Relationships ==============

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============== Accessors ==============

    public function getProjectAttribute()
    {
        return $this->task?->project;
    }

    public function getUserNameAttribute(): string
    {
        return $this->user->name ?? 'Unknown User';
    }

    public function getUserAvatarAttribute(): ?string
    {
        return $this->user->profile?->avatar;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
