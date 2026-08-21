<?php

namespace App\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReaction extends Model
{
    use HasFactory;

    protected $table = 'project_reactions';

    protected $fillable = [
        'project_id',
        'user_id',
        'reaction_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public static function getReactionIcon(string $type): string
    {
        return match ($type) {
            'like' => '👍',
            'love' => '❤️',
            'dislike' => '👎',
            default => '👍',
        };
    }
}
