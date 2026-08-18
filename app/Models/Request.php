<?php

namespace App\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;


class Request extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'sender_id', 'receiver_id', 'project_id', 'type',
        'status', 'message', 'responded_at', 'responded_by', 'role'
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeJoinRequests(Builder $query): Builder
    {
        return $query->where('type', 'join_request');
    }

    public function scopeInvitations(Builder $query): Builder
    {
        return $query->where('type', 'invitation');
    }
}
