<?php

namespace App\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'project_comments';

    protected $fillable = [
        'project_id',
        'user_id',
        'content',
        'parent_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ProjectComment::class, 'parent_id');
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
