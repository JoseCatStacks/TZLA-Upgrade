<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AuthNonce extends Model
{
    protected $fillable = [
        'wallet_address',
        'nonce',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
