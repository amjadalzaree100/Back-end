<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Report extends Model
{
    use HasFactory;

    protected $table = 'reports';

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'reason',
        'details',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }

    /**
     * Scope to reports created within an optional date range (half-open: includes
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
