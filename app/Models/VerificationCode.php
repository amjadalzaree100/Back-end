<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    protected $fillable = ['email', 'code', 'expires_at', 'is_used'];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }
}
