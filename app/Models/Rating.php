<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    protected $table = 'ratings';

    protected $fillable = [
        'rater_id',
        'rated_user_id',
        'project_id',
        'rating',
    ];

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id')->withTrashed();
    }

    public function ratedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user_id')->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
